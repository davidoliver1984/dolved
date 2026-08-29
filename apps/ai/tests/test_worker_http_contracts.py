import copy
import json
from pathlib import Path
from typing import Any

import httpx
from jsonschema import Draft202012Validator, FormatChecker

from app.deletion.client import DocumentDeletionClient
from app.ingestion.protocol_client import IngestionProtocolClient
from app.ingestion.signing import IngestionWorkerSigner

CONTRACT_ROOT = Path("/contracts/http")
CONTRACT_DIRECTORIES = (
    CONTRACT_ROOT / "ingestion-worker" / "v1",
    CONTRACT_ROOT / "document-deletion-worker" / "v1",
)
EXPECTED_OPERATIONS = {
    "ingestion.claim",
    "ingestion.lease.renew",
    "ingestion.extraction-artifact.authorise",
    "ingestion.extraction-artifact.acknowledge",
    "ingestion.chunks.submit",
    "ingestion.chunks.seal",
    "ingestion.attempt.resume",
    "ingestion.publication.authorise",
    "ingestion.complete",
    "ingestion.fail",
    "ingestion.attempt.cancel",
    "document.deletion.claim",
    "document.deletion.complete",
    "document.deletion.fail",
}


def _load(path: Path) -> dict[str, Any]:
    value = json.loads(path.read_text(encoding="utf-8"))
    assert isinstance(value, dict)
    return value


def _mutate(payload: dict[str, Any], mutation: dict[str, Any]) -> dict[str, Any]:
    changed = copy.deepcopy(payload)
    path = mutation["path"]
    assert isinstance(path, list) and path
    parent: dict[str, Any] = changed
    for segment in path[:-1]:
        value = parent[segment]
        assert isinstance(value, dict)
        parent = value
    final = path[-1]
    if mutation["action"] == "remove":
        del parent[final]
    else:
        parent[final] = mutation["value"]
    return changed


def test_shared_worker_operation_vectors_validate_and_fail_closed() -> None:
    observed: set[str] = set()
    for directory in CONTRACT_DIRECTORIES:
        fixture = _load(directory / "worker-operation-vectors.json")
        assert fixture["contract_version"] == 1
        for operation in fixture["operations"]:
            observed.add(operation["purpose"])
            for target in ("request", "response"):
                schema = _load(directory / operation[f"{target}_schema"])
                validator = Draft202012Validator(
                    schema,
                    format_checker=FormatChecker(),
                )
                validator.validate(operation[target])
            for mutation in operation["invalid"]:
                target = mutation["target"]
                schema = _load(directory / operation[f"{target}_schema"])
                validator = Draft202012Validator(
                    schema,
                    format_checker=FormatChecker(),
                )
                assert not validator.is_valid(_mutate(operation[target], mutation)), (
                    f"{operation['name']} accepted {mutation['case']}"
                )

    assert observed == EXPECTED_OPERATIONS


def test_claim_fixtures_also_match_the_authoritative_event_contracts() -> None:
    pairs = (
        (
            CONTRACT_DIRECTORIES[0],
            CONTRACT_ROOT.parent
            / "events"
            / "document-ingestion-requested"
            / "v1.schema.json",
        ),
        (
            CONTRACT_DIRECTORIES[1],
            CONTRACT_ROOT.parent
            / "events"
            / "document-deletion-requested"
            / "v1.schema.json",
        ),
    )
    for directory, event_schema_path in pairs:
        fixture = _load(directory / "worker-operation-vectors.json")
        operation = fixture["operations"][0]
        claim = operation["request"]
        validator = Draft202012Validator(
            _load(event_schema_path),
            format_checker=FormatChecker(),
        )
        validator.validate(claim)
        for mutation in operation["invalid"]:
            if mutation["target"] == "request":
                assert not validator.is_valid(_mutate(claim, mutation))


def test_python_signer_reproduces_every_shared_worker_signature() -> None:
    fixture = _load(CONTRACT_DIRECTORIES[0] / "signing-vectors.json")
    signer = IngestionWorkerSigner(
        fixture["key_id"],
        fixture["secret_base64"],
    )

    assert {vector["purpose"] for vector in fixture["vectors"]} == EXPECTED_OPERATIONS
    for vector in fixture["vectors"]:
        signed = signer.sign(
            timestamp=vector["timestamp"],
            method=vector["method"],
            request_path=vector["path"],
            body=vector["body"].encode("utf-8"),
            event_id=vector["event_id"],
            purpose=vector["purpose"],
        )
        assert signed.signature == vector["signature"]


def test_python_clients_emit_the_shared_canonical_request_shapes() -> None:
    ingestion_fixture = _load(CONTRACT_DIRECTORIES[0] / "worker-operation-vectors.json")
    deletion_fixture = _load(CONTRACT_DIRECTORIES[1] / "worker-operation-vectors.json")
    operations = {
        operation["purpose"]: operation
        for fixture in (ingestion_fixture, deletion_fixture)
        for operation in fixture["operations"]
    }
    observed: dict[str, dict[str, Any]] = {}

    def respond(request: httpx.Request) -> httpx.Response:
        purpose = request.headers["X-Ingestion-Worker-Purpose"]
        payload = json.loads(request.content)
        assert isinstance(payload, dict)
        observed[purpose] = payload
        return httpx.Response(200, json=operations[purpose]["response"])

    signer = IngestionWorkerSigner(
        "local-v1",
        "MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=",
    )
    transport = httpx.MockTransport(respond)
    ingestion = IngestionProtocolClient(
        base_url="http://api:8000",
        timeout_seconds=1,
        signer=signer,
        client=httpx.Client(transport=transport),
        max_attempts=1,
    )
    by_name = {
        operation["name"]: operation for operation in ingestion_fixture["operations"]
    }
    claim = by_name["ingestion-claim"]["request"]
    ingestion.claim(
        raw_body=json.dumps(claim, ensure_ascii=False, separators=(",", ":")),
        event_id=claim["event_id"],
    )
    context = {
        key: by_name["ingestion-lease-renew"]["request"][key]
        for key in ("event_id", "workspace_id", "document_id", "lease_token")
    }
    context["lease_generation"] = by_name["ingestion-extraction-artifact-authorise"][
        "request"
    ]["lease_generation"]
    ingestion.renew(context)
    ingestion.authorise_extraction_artifact(context)
    acknowledgement = by_name["ingestion-extraction-artifact-acknowledge"]["request"]
    ingestion.acknowledge_extraction_artifact(
        context,
        {
            key: value
            for key, value in acknowledgement.items()
            if key not in {*context, "contract_version", "lease_generation"}
        },
    )
    ingestion.submit_chunks(
        context,
        by_name["ingestion-chunks-submit"]["request"]["chunks"],
    )
    ingestion.seal(
        context,
        {
            key: by_name["ingestion-chunks-seal"]["request"][key]
            for key in (
                "expected_chunk_count",
                "chunk_manifest_digest",
                "configuration_fingerprint",
            )
        },
    )
    resume = by_name["ingestion-attempt-resume"]["request"]
    ingestion.resume(context, cursor=resume["cursor"], limit=resume["limit"])
    authorise = by_name["ingestion-publication-authorise"]["request"]
    ingestion.authorise_publication(
        context,
        {
            key: value
            for key, value in authorise.items()
            if key not in {*context, "contract_version"}
        },
    )
    complete = by_name["ingestion-complete"]["request"]
    ingestion.complete(
        context,
        {
            key: value
            for key, value in complete.items()
            if key not in {*context, "contract_version", "publication_verified"}
        },
    )
    failure = by_name["ingestion-fail"]["request"]
    ingestion.fail(
        context,
        failure_code=failure["failure_code"],
        failure_message=failure["failure_message"],
        usage=failure["usage"],
    )
    ingestion.cancel(
        context,
        by_name["ingestion-attempt-cancel"]["request"]["usage"],
    )

    deletion = DocumentDeletionClient(
        base_url="http://api:8000",
        timeout_seconds=1,
        signer=signer,
        client=httpx.Client(transport=transport),
    )
    deletion_by_name = {
        operation["name"]: operation for operation in deletion_fixture["operations"]
    }
    deletion_claim = deletion_by_name["document-deletion-claim"]["request"]
    deletion.claim(
        event_id=deletion_claim["event_id"],
        raw_body=json.dumps(
            deletion_claim,
            ensure_ascii=False,
            separators=(",", ":"),
        ),
    )
    deletion_context = {
        key: deletion_by_name["document-deletion-complete"]["request"][key]
        for key in ("event_id", "workspace_id", "document_id", "lease_token")
    }
    deletion.complete(
        deletion_context,
        deletion_by_name["document-deletion-complete"]["request"]["scopes"],
    )
    deletion_failure = deletion_by_name["document-deletion-fail"]["request"]
    deletion.fail(
        deletion_context,
        classification=deletion_failure["classification"],
        failure_code=deletion_failure["failure_code"],
        failure_message=deletion_failure["failure_message"],
    )

    assert observed == {
        purpose: operation["request"] for purpose, operation in operations.items()
    }
