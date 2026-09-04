import copy
import importlib.util
import inspect
import json
import sys
from collections import Counter
from pathlib import Path
from types import SimpleNamespace
from uuid import UUID

import pytest
from jsonschema import Draft202012Validator, FormatChecker

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "scripts/evaluation/run_r28_s04_live.py"
POLICY = ROOT / "tests/evaluation/policies/v1/r28-s04-live-evaluation-policy.json"
RUN_ID = "R28-S04-LIVE-V4-DRY-RUN-0001"


def module():
    spec = importlib.util.spec_from_file_location("run_r28_s04_live", SCRIPT)
    assert spec and spec.loader
    value = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = value
    spec.loader.exec_module(value)
    return value


def policy_value():
    runner = module()
    return runner, runner.load_policy(POLICY)


def authorization(runner, policy, *, commit="1" * 40):
    return {
        "schema_version": runner.AUTHORIZATION_SCHEMA,
        "authorization_id": "R28-S04-AUTH-TEST-0001",
        "authorised": True,
        "run_id": RUN_ID,
        "execution_commit": commit,
        "population_identity": policy["population_identity"],
        "population_digest": policy["population_digest"],
        "policy_sha256": runner.sha256(POLICY),
        "execution_profile_digest": policy["execution_profile_digest"],
        "provider_profiles": policy["provider_profiles"],
        "ceilings": policy["ceilings"],
        "approved_by": "David Oliver",
        "approved_on": "2026-09-04",
        "selective_rerun_authorised": False,
    }


def request(runner, policy, stage="generation", subject="case:v1"):
    profile = policy["provider_profiles"][stage]
    limit = policy["stage_limits"][stage]
    return runner.DispatchRequest(
        request_id=runner.request_id(RUN_ID, stage, subject),
        stage=stage,
        provider=profile["provider"],
        model=profile["model"],
        adapter=profile["adapter"],
        pricing_snapshot=profile["pricing_snapshot"],
        request_digest=runner.digest_value({"stage": stage, "subject": subject}),
        maximum_input_tokens_per_attempt=limit["input_tokens_per_attempt"],
        maximum_output_tokens_per_attempt=limit["output_tokens_per_attempt"],
    )


def receipt(runner, *, inputs=1, outputs=0):
    value = {"answer": "ok"}
    return runner.ProviderResult(
        value,
        runner.DispatchReceipt(
            response_digest=runner.digest_value(value),
            input_tokens=inputs,
            output_tokens=outputs,
            provider_request_id_digest=runner.digest_value("opaque-provider-id"),
        ),
    )


def gateway(tmp_path, runner, policy):
    identity = runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64)
    ledger = runner.AppendOnlyRunLedger(tmp_path / RUN_ID, identity, create=True)
    budget = runner.HardBudget(policy["ceilings"], monotonic=lambda: 0.0)
    return runner.BudgetedDispatchGateway(policy, budget, ledger), ledger


def test_zero_provider_dry_run_accounts_for_all_routes_and_limits() -> None:
    runner, policy = policy_value()
    result = runner.validate(ROOT, policy, RUN_ID)
    assert result["cases"] == 74
    assert result["utterances"] == 148
    assert result["outcomes"] == {
        "EVIDENCE_FOUND": 86,
        "INSUFFICIENT_EVIDENCE": 10,
        "NO_RETRIEVAL_CANDIDATES": 10,
        "NO_ELIGIBLE_EVIDENCE": 12,
        "CLARIFICATION_REQUIRED": 10,
        "COMPARISON_SCOPE_INCOMPLETE": 10,
        "TEMPORAL_SCOPE_UNRESOLVED": 10,
    }
    assert result["routing"] == {
        "corpus_embedding": 8,
        "query_embedding": 1,
        "reranker": 140,
        "generation": 86,
        "judge": 86,
    }
    assert result["budget"] | {"provider_calls": result["provider_calls"]} == {
        "base_provider_requests": 321,
        "physical_attempts": 642,
        "input_tokens": 7_416_320,
        "output_tokens": 1_056_768,
        "maximum_planned_cost_usd": "3.30086400",
        "provider_calls": 0,
    }


def test_local_sparse_batches_are_stable_complete_and_bounded() -> None:
    runner = module()
    items = [
        {"chunk_id": str(UUID(int=index + 1)), "text": f"chunk {index}"}
        for index in range(1000)
    ]
    batches = runner.local_sparse_batches(items)
    assert len(batches) == 63
    assert [len(batch) for batch in batches] == ([16] * 62) + [8]
    assert [item for batch in batches for item in batch] == items
    assert all(len(batch) <= runner.LOCAL_SPARSE_BATCH_SIZE for batch in batches)


def test_local_sparse_batches_reject_empty_and_duplicate_sources() -> None:
    runner = module()
    with pytest.raises(ValueError, match="at least one"):
        runner.local_sparse_batches([])
    duplicate = str(UUID(int=1))
    with pytest.raises(ValueError, match="unique"):
        runner.local_sparse_batches(
            [
                {"chunk_id": duplicate, "text": "one"},
                {"chunk_id": duplicate, "text": "two"},
            ]
        )


def sparse_result(*, source_ids, profile, purpose, indices=(1,), values=(0.5,)):
    return SimpleNamespace(
        profile=profile,
        profile_fingerprint=profile.fingerprint(),
        purpose=purpose,
        encodings=tuple(
            SimpleNamespace(
                source_id=source_id,
                vector=SimpleNamespace(indices=indices, values=values),
            )
            for source_id in source_ids
        ),
    )


def test_sparse_batch_validation_preserves_exact_vector_order() -> None:
    runner = module()
    profile = SimpleNamespace(fingerprint=lambda: "profile")
    source_ids = [UUID(int=1), UUID(int=2)]
    result = sparse_result(
        source_ids=source_ids,
        profile=profile,
        purpose="document",
        indices=(4, 9),
        values=(0.25, 0.75),
    )
    assert runner.validate_sparse_batch_result(
        source_ids,
        result,
        expected_profile=profile,
        expected_purpose="document",
    ) == [{4: 0.25, 9: 0.75}, {4: 0.25, 9: 0.75}]


@pytest.mark.parametrize("mutation", ["missing", "reordered", "duplicate"])
def test_sparse_batch_validation_rejects_identity_mutations(mutation) -> None:
    runner = module()
    profile = SimpleNamespace(fingerprint=lambda: "profile")
    expected = [UUID(int=1), UUID(int=2)]
    actual = {
        "missing": expected[:1],
        "reordered": list(reversed(expected)),
        "duplicate": [expected[0], expected[0]],
    }[mutation]
    result = sparse_result(source_ids=actual, profile=profile, purpose="document")
    with pytest.raises(ValueError, match="ordering/identity"):
        runner.validate_sparse_batch_result(
            expected,
            result,
            expected_profile=profile,
            expected_purpose="document",
        )


def test_sparse_batch_validation_rejects_profile_purpose_and_vector_drift() -> None:
    runner = module()
    profile = SimpleNamespace(fingerprint=lambda: "profile")
    source_ids = [UUID(int=1)]
    wrong_purpose = sparse_result(
        source_ids=source_ids, profile=profile, purpose="query"
    )
    with pytest.raises(ValueError, match="profile or purpose"):
        runner.validate_sparse_batch_result(
            source_ids,
            wrong_purpose,
            expected_profile=profile,
            expected_purpose="document",
        )
    malformed = sparse_result(
        source_ids=source_ids,
        profile=profile,
        purpose="document",
        indices=(1, 2),
        values=(0.5,),
    )
    with pytest.raises(ValueError, match="vector shape"):
        runner.validate_sparse_batch_result(
            source_ids,
            malformed,
            expected_profile=profile,
            expected_purpose="document",
        )


def test_local_sparse_encoding_batches_and_recombines_deterministically() -> None:
    runner = module()
    profile = SimpleNamespace(fingerprint=lambda: "profile")
    items = [
        {"chunk_id": str(UUID(int=index + 1)), "text": f"chunk {index}"}
        for index in range(33)
    ]

    class Encoder:
        def __init__(self):
            self.requests = []

        def encode(self, sparse_request):
            self.requests.append(sparse_request)
            return SimpleNamespace(
                profile=profile,
                profile_fingerprint="profile",
                purpose=sparse_request.purpose,
                encodings=tuple(
                    SimpleNamespace(
                        source_id=item.source_id,
                        vector=SimpleNamespace(
                            indices=(item.source_id.int,), values=(1.0,)
                        ),
                    )
                    for item in sparse_request.items
                ),
            )

    encoder = Encoder()
    vectors = runner.encode_local_sparse_items(
        items,
        encoder=encoder,
        profile=profile,
        purpose="document",
        subject="documents",
        workspace_id=UUID(int=100),
        input_type=SimpleNamespace,
        request_type=SimpleNamespace,
    )
    assert [len(request.items) for request in encoder.requests] == [16, 16, 1]
    assert [next(iter(vector)) for vector in vectors] == list(range(1, 34))
    assert [request.correlation_id for request in encoder.requests] == [
        runner.uuid5(
            runner.NAMESPACE_URL,
            f"r28-s04:sparse:documents:batch-{number:04d}",
        )
        for number in range(1, 4)
    ]


def test_local_sparse_encoding_stops_on_partial_batch_failure() -> None:
    runner = module()
    profile = SimpleNamespace(fingerprint=lambda: "profile")
    items = [
        {"chunk_id": str(UUID(int=index + 1)), "text": f"chunk {index}"}
        for index in range(17)
    ]

    class Encoder:
        calls = 0

        def encode(self, sparse_request):
            self.calls += 1
            if self.calls == 2:
                raise RuntimeError("local sparse batch failed")
            return sparse_result(
                source_ids=[item.source_id for item in sparse_request.items],
                profile=profile,
                purpose=sparse_request.purpose,
            )

    encoder = Encoder()
    with pytest.raises(RuntimeError, match="local sparse batch failed"):
        runner.encode_local_sparse_items(
            items,
            encoder=encoder,
            profile=profile,
            purpose="document",
            subject="documents",
            workspace_id=UUID(int=100),
            input_type=SimpleNamespace,
            request_type=SimpleNamespace,
        )
    assert encoder.calls == 2


@pytest.mark.parametrize(
    ("key", "value", "message"),
    [
        ("base_provider_requests", 0, "base_provider_requests"),
        ("physical_attempts", 1, "physical_attempts"),
        ("input_tokens", 1, "input_tokens"),
        ("output_tokens", 1, "output_tokens"),
        ("cost_usd", "0.00000001", "cost"),
    ],
)
def test_every_ceiling_fails_before_dispatch(tmp_path, key, value, message) -> None:
    runner, policy = policy_value()
    policy["ceilings"][key] = value
    gate, _ = gateway(tmp_path, runner, policy)
    calls = 0

    def provider():
        nonlocal calls
        calls += 1
        return receipt(runner)

    with pytest.raises(RuntimeError, match=message):
        gate.generate(request(runner, policy), provider)
    assert calls == 0


def test_wall_clock_fails_before_dispatch(tmp_path) -> None:
    runner, policy = policy_value()
    identity = runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64)
    ledger = runner.AppendOnlyRunLedger(tmp_path / RUN_ID, identity, create=True)
    readings = iter((0.0, 10800.0))
    budget = runner.HardBudget(policy["ceilings"], monotonic=lambda: next(readings))
    gate = runner.BudgetedDispatchGateway(policy, budget, ledger)
    calls = 0

    def provider():
        nonlocal calls
        calls += 1
        return receipt(runner)

    with pytest.raises(RuntimeError, match="wall_clock"):
        gate.generate(request(runner, policy), provider)
    assert calls == 0


def test_all_paid_paths_use_the_same_budgeted_gateway(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    methods = ("corpus_embedding", "query_embedding", "rerank_side", "generate")
    stages = ("corpus_embedding", "query_embedding", "reranker", "generation")
    for method, stage in zip(methods, stages, strict=True):
        getattr(gate, method)(
            request(runner, policy, stage, stage), lambda: receipt(runner)
        )
    assert (
        sum(event["event_type"] == "request_completed" for event in ledger.events) == 4
    )


@pytest.mark.asyncio
async def test_judge_path_is_budgeted_and_recorded(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)

    async def provider():
        return receipt(runner, outputs=1)

    await gate.judge(request(runner, policy, "judge", "judge"), provider)
    assert (
        ledger.request_state(request(runner, policy, "judge", "judge").request_id)
        == "completed"
    )


@pytest.mark.asyncio
async def test_judge_invariant_failure_retries_once_and_accounts_both_responses(
    tmp_path,
) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    attempts = 0

    async def provider():
        nonlocal attempts
        attempts += 1
        if attempts == 1:
            failed_value = {"status": "FAILED", "failure_code": "part_indices_mismatch"}
            failed_receipt = runner.DispatchReceipt(
                response_digest=runner.digest_value(failed_value),
                input_tokens=321,
                cached_input_tokens=21,
                output_tokens=45,
            )
            raise runner.RetryableDispatchError(
                "judge_contract_invariant",
                provider_error_code="part_indices_mismatch",
                receipt=failed_receipt,
            )
        return receipt(runner, inputs=300, outputs=40)

    item = request(runner, policy, "judge", "judge-invariant")
    await gate.judge(item, provider)
    assert attempts == 2
    assert gate.budget.actual_attempts == 2
    assert gate.budget.actual_input_tokens == 621
    assert gate.budget.actual_output_tokens == 85
    failure = next(
        event for event in ledger.events if event["event_type"] == "attempt_failed"
    )
    assert failure["provider_error_code"] == "part_indices_mismatch"
    assert failure["receipt"]["input_tokens"] == 321
    assert failure["receipt"]["cached_input_tokens"] == 21
    assert failure["receipt"]["output_tokens"] == 45
    ledger_text = ledger.events_path.read_text()
    assert "judge_contract_invariant" not in ledger_text
    assert "raw_response" not in ledger_text


@pytest.mark.asyncio
async def test_two_judge_invariant_failures_stop_after_governed_retry(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    attempts = 0

    async def provider():
        nonlocal attempts
        attempts += 1
        failed_value = {
            "status": "FAILED",
            "failure_code": "completeness_missing",
            "attempt": attempts,
        }
        raise runner.RetryableDispatchError(
            "judge_contract_invariant",
            provider_error_code="completeness_missing",
            receipt=runner.DispatchReceipt(
                response_digest=runner.digest_value(failed_value),
                input_tokens=100,
                output_tokens=10,
            ),
        )

    with pytest.raises(runner.RetryableDispatchError):
        await gate.judge(request(runner, policy, "judge", "twice-invalid"), provider)
    assert attempts == 2
    assert gate.budget.actual_attempts == 2
    assert gate.budget.actual_input_tokens == 200
    assert gate.budget.actual_output_tokens == 20
    assert [event["event_type"] for event in ledger.events].count("attempt_failed") == 2


@pytest.mark.asyncio
async def test_valid_low_judge_scores_are_not_retried(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    calls = 0

    async def provider():
        nonlocal calls
        calls += 1
        value = {"status": "COMPLETED", "scores": {"ANSWER_COMPLETENESS": 0}}
        return runner.ProviderResult(
            value,
            runner.DispatchReceipt(
                response_digest=runner.digest_value(value),
                input_tokens=100,
                output_tokens=10,
            ),
        )

    await gate.judge(request(runner, policy, "judge", "valid-low-score"), provider)
    assert calls == 1
    assert gate.budget.actual_attempts == 1
    assert not any(event["event_type"] == "attempt_failed" for event in ledger.events)


def test_comparison_sides_are_independent_dispatches() -> None:
    runner, policy = policy_value()
    population = json.loads((ROOT / policy["population_path"]).read_text())
    routes = runner.route_plan(population, policy, RUN_ID)
    rerank = [route for route in routes if route["stage"] == "reranker"]
    assert len(rerank) == 140
    subjects = {route["subject"] for route in rerank}
    assert any(subject.endswith(":PRIMARY") for subject in subjects)
    assert any(subject.endswith(":COMPARISON") for subject in subjects)
    assert len({route["request_id"] for route in rerank}) == 140


def lightweight_chunks(runner):
    return [
        {
            "chunk_id": str(runner.uuid5(runner.NAMESPACE_URL, f"batch-proof:{index}")),
            "text": f"bounded corpus batch proof {index}",
        }
        for index in range(1000)
    ]


def test_corpus_batches_have_exact_approved_shape_and_stable_request_ids() -> None:
    runner, policy = policy_value()
    batches = runner.corpus_batches(lightweight_chunks(runner))
    assert tuple(map(len, batches)) == (128, 128, 128, 128, 128, 128, 128, 104)
    population = json.loads((ROOT / policy["population_path"]).read_text())
    first = runner.route_plan(population, policy, RUN_ID)
    second = runner.route_plan(population, policy, RUN_ID)
    corpus = [item for item in first if item["stage"] == "corpus_embedding"]
    assert first == second
    assert [item["subject"] for item in corpus] == [
        f"frozen-primary-corpus:batch-{number:04d}" for number in range(1, 9)
    ]
    assert len({item["request_id"] for item in corpus}) == 8


@pytest.mark.parametrize(
    ("limit_name", "limit_value", "message"),
    [
        ("maximum_items_per_request", 127, "item_limit"),
        ("input_tokens_per_attempt", 1, "token_allowance"),
        ("maximum_request_bytes_per_attempt", 1, "byte_allowance"),
    ],
)
def test_corpus_batch_preflight_fails_closed_before_dispatch(
    limit_name, limit_value, message
) -> None:
    runner, policy = policy_value()
    payload = runner.embedding_payload(
        runner.corpus_batches(lightweight_chunks(runner))[0],
        purpose="document",
        workspace_id=runner.uuid5(runner.NAMESPACE_URL, "workspace"),
        correlation_subject="batch-0001",
    )
    limits = copy.deepcopy(policy["stage_limits"]["corpus_embedding"])
    limits[limit_name] = limit_value
    with pytest.raises(ValueError, match=message):
        runner.measure_corpus_batch(ROOT, payload, limits)


@pytest.mark.parametrize("mutation", ["missing", "duplicate", "reordered", "dimension"])
def test_embedding_recombination_rejects_invalid_provider_vectors(mutation) -> None:
    runner = module()
    records = lightweight_chunks(runner)[:3]
    payload = runner.embedding_payload(
        records,
        purpose="document",
        workspace_id=runner.uuid5(runner.NAMESPACE_URL, "workspace"),
    )
    ids = [item["source_id"] for item in payload["items"]]
    vectors = [[1.0] + [0.0] * 1023 for _ in ids]
    if mutation == "missing":
        ids, vectors = ids[:-1], vectors[:-1]
    elif mutation == "duplicate":
        ids[1] = ids[0]
    elif mutation == "reordered":
        ids[0], ids[1] = ids[1], ids[0]
    else:
        vectors[0] = [1.0, 0.0]
    with pytest.raises(ValueError):
        runner._vectors(payload, {"source_ids": ids, "vectors": vectors})


@pytest.mark.parametrize(
    ("status", "expected_code"),
    [
        (400, "invalid_input"),
        (401, "authentication_failed"),
        (403, "authentication_failed"),
        (413, "input_too_large"),
        (422, "invalid_input"),
        (429, "rate_limited"),
        (503, "provider_unavailable"),
    ],
)
def test_safe_provider_failure_records_only_allowlisted_status_and_code(
    tmp_path, status, expected_code
) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)

    class SafeFailure(RuntimeError):
        provider_status = status

    with pytest.raises(SafeFailure):
        gate.corpus_embedding(
            request(runner, policy, "corpus_embedding", "safe-failure"),
            lambda: (_ for _ in ()).throw(SafeFailure("sensitive provider body")),
        )
    failure = next(
        item for item in ledger.events if item["event_type"] == "attempt_failed"
    )
    assert failure["provider_status"] == status
    assert failure["provider_error_code"] == expected_code
    assert "sensitive provider body" not in ledger.events_path.read_text()


def test_retry_is_one_only_and_has_durable_lineage(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    attempts = 0

    def provider():
        nonlocal attempts
        attempts += 1
        if attempts == 1:
            raise runner.RetryableDispatchError("429")
        return receipt(runner, outputs=1)

    gate.generate(request(runner, policy), provider)
    assert attempts == 2
    assert gate.budget.unknown_usage_attempts == 1
    assert [e["event_type"] for e in ledger.events].count("attempt_started") == 2
    assert [e["event_type"] for e in ledger.events].count("attempt_failed") == 1
    failure = next(e for e in ledger.events if e["event_type"] == "attempt_failed")
    assert failure["input_tokens"] is None
    assert failure["actual_cost_usd"] is None
    with pytest.raises(RuntimeError, match="duplicate"):
        gate.generate(request(runner, policy), provider)


def test_retry_overflow_stops_after_two_attempts(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    attempts = 0

    def provider():
        nonlocal attempts
        attempts += 1
        raise runner.RetryableDispatchError("timeout")

    with pytest.raises(runner.RetryableDispatchError):
        gate.generate(request(runner, policy), provider)
    assert attempts == 2
    assert ledger.request_state(request(runner, policy).request_id) == "incomplete"


def test_interrupted_attempt_is_preserved_and_cannot_repeat(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    item = request(runner, policy)
    ledger.append("request_reserved", {"request_id": item.request_id})
    ledger.append(
        "attempt_started",
        {"request_id": item.request_id, "attempt_id": "a1", "attempt": 1},
    )
    reopened = runner.AppendOnlyRunLedger(
        ledger.run_dir,
        runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64),
        create=False,
    )
    assert reopened.request_state(item.request_id) == "interrupted"
    with pytest.raises(RuntimeError, match="interrupted"):
        gate.generate(item, lambda: receipt(runner))


def test_interrupted_attempt_consumes_physical_authority_on_restore(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    item = request(runner, policy)
    gate._prepare(item)
    ledger.append(
        "attempt_started",
        {"request_id": item.request_id, "attempt_id": "a1", "attempt": 1},
    )

    restored = runner.restore_budget(policy, ledger)

    assert restored.reserved_attempts == 2
    assert restored.actual_attempts == 1
    assert restored.unknown_usage_attempts == 1


def test_identity_mismatch_and_immutable_output_fail_closed(tmp_path) -> None:
    runner, policy = policy_value()
    identity = runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64)
    run_dir = tmp_path / RUN_ID
    runner.AppendOnlyRunLedger(run_dir, identity, create=True)
    with pytest.raises(FileExistsError):
        runner.AppendOnlyRunLedger(run_dir, identity, create=True)
    changed = dict(identity, population_digest="0" * 64)
    with pytest.raises(ValueError, match="identity"):
        runner.AppendOnlyRunLedger(run_dir, changed, create=False)


def test_hash_chain_tampering_is_rejected(tmp_path) -> None:
    runner, policy = policy_value()
    identity = runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64)
    ledger = runner.AppendOnlyRunLedger(tmp_path / RUN_ID, identity, create=True)
    ledger.append("request_reserved", {"request_id": "req-one"})
    text = ledger.events_path.read_text().replace("req-one", "req-two")
    ledger.events_path.write_text(text)
    with pytest.raises(ValueError, match="modified"):
        runner.AppendOnlyRunLedger(ledger.run_dir, identity, create=False)


def test_policy_and_bound_identity_mutations_fail_closed(tmp_path) -> None:
    runner, _ = policy_value()
    original = json.loads(POLICY.read_text())
    mutations = (
        ("provider_execution_authorised", True, "self-referential"),
        ("execution_commit", "0" * 40, "self-referential"),
        ("execution_profile_digest", "0" * 64, "profile"),
    )
    for key, value, message in mutations:
        changed = json.loads(json.dumps(original))
        changed[key] = value
        path = tmp_path / f"{key}.json"
        path.write_text(json.dumps(changed))
        with pytest.raises(ValueError, match=message):
            runner.load_policy(path)


def test_provider_usage_and_response_identity_are_required(tmp_path) -> None:
    runner, policy = policy_value()
    gate, _ = gateway(tmp_path, runner, policy)
    invalid = runner.ProviderResult(
        {},
        runner.DispatchReceipt(response_digest="bad", input_tokens=1, output_tokens=1),
    )
    with pytest.raises(ValueError, match="response digest"):
        gate.generate(request(runner, policy), lambda: invalid)


def test_per_request_token_overrun_fails_closed(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    item = request(runner, policy)
    too_large = receipt(
        runner,
        inputs=item.maximum_input_tokens_per_attempt + 1,
        outputs=item.maximum_output_tokens_per_attempt,
    )
    with pytest.raises(RuntimeError, match="provider_input_tokens"):
        gate.generate(item, lambda: too_large)
    assert ledger.request_state(item.request_id) == "incomplete"


def test_provider_profile_mismatch_fails_before_dispatch(tmp_path) -> None:
    runner, policy = policy_value()
    gate, _ = gateway(tmp_path, runner, policy)
    item = request(runner, policy)
    changed = runner.DispatchRequest(**{**runner.asdict(item), "model": "other"})
    calls = 0

    def provider():
        nonlocal calls
        calls += 1
        return receipt(runner)

    with pytest.raises(ValueError, match="identity mismatch"):
        gate.generate(changed, provider)
    assert calls == 0


def test_per_request_authority_cannot_be_reduced_to_evade_accounting(tmp_path) -> None:
    runner, policy = policy_value()
    gate, _ = gateway(tmp_path, runner, policy)
    item = request(runner, policy)
    changed = runner.DispatchRequest(
        **{**runner.asdict(item), "maximum_input_tokens_per_attempt": 0}
    )
    calls = 0

    def provider():
        nonlocal calls
        calls += 1
        return receipt(runner)

    with pytest.raises(ValueError, match="token authority"):
        gate.generate(changed, provider)
    assert calls == 0


def test_concurrent_dispatch_is_rejected_before_call(tmp_path) -> None:
    runner, policy = policy_value()
    gate, _ = gateway(tmp_path, runner, policy)
    gate._in_flight = True
    calls = 0

    def provider():
        nonlocal calls
        calls += 1
        return receipt(runner)

    with pytest.raises(RuntimeError, match="concurrency"):
        gate.generate(request(runner, policy), provider)
    assert calls == 0


def test_deterministic_routes_never_create_provider_requests() -> None:
    runner, policy = policy_value()
    population = json.loads((ROOT / policy["population_path"]).read_text())
    routes = runner.route_plan(population, policy, RUN_ID)
    subjects = {route["subject"] for route in routes}
    for case in population["cases"]:
        if case["expected_outcome"]["retrieval"] in {
            "NO_ELIGIBLE_EVIDENCE",
            "CLARIFICATION_REQUIRED",
            "COMPARISON_SCOPE_INCOMPLETE",
            "TEMPORAL_SCOPE_UNRESOLVED",
        }:
            for variant in case["variants"]:
                subject = f"{case['case_id']}:{variant['variant_id']}"
                assert not any(
                    route_subject.startswith(subject) for route_subject in subjects
                )


def test_exact_external_authorization_is_one_use_and_resume_bound(tmp_path) -> None:
    runner, policy = policy_value()
    value = authorization(runner, policy)
    path = tmp_path / "authorization.json"
    path.write_text(json.dumps(value))
    loaded, identity = runner.load_authorization(
        path,
        policy=policy,
        policy_path=POLICY,
        run_id=RUN_ID,
        supplied_commit="1" * 40,
    )
    runner.consume_authorization(tmp_path / "runs", loaded, identity, resume=False)
    with pytest.raises(FileExistsError):
        runner.consume_authorization(tmp_path / "runs", loaded, identity, resume=False)
    runner.consume_authorization(tmp_path / "runs", loaded, identity, resume=True)


def test_authorization_matches_the_versioned_json_schema() -> None:
    runner, policy = policy_value()
    schema = json.loads((ROOT / policy["authorization_schema_path"]).read_text())
    Draft202012Validator(schema, format_checker=FormatChecker()).validate(
        authorization(runner, policy)
    )


@pytest.mark.parametrize(
    ("key", "value", "message"),
    [
        ("execution_commit", "0" * 40, "execution_commit"),
        ("population_digest", "0" * 64, "population_digest"),
        ("policy_sha256", "0" * 64, "policy_sha256"),
        ("execution_profile_digest", "0" * 64, "execution_profile_digest"),
        ("authorised", False, "false or malformed"),
    ],
)
def test_authorization_identity_mutations_fail_closed(tmp_path, key, value, message):
    runner, policy = policy_value()
    changed = authorization(runner, policy)
    changed[key] = value
    path = tmp_path / f"{key}.json"
    path.write_text(json.dumps(changed))
    with pytest.raises(ValueError, match=message):
        runner.load_authorization(
            path,
            policy=policy,
            policy_path=POLICY,
            run_id=RUN_ID,
            supplied_commit="1" * 40,
        )


def test_repository_requires_exact_head_origin_and_clean_tree(monkeypatch) -> None:
    runner = module()
    values = {
        ("rev-parse", "HEAD"): "1" * 40,
        ("rev-parse", "origin/main"): "1" * 40,
        ("status", "--porcelain=v1", "--untracked-files=no"): "",
    }
    monkeypatch.setattr(runner, "git", lambda _root, *args: values[args])
    runner.verify_repository(ROOT, "1" * 40)
    values[("rev-parse", "HEAD")] = "0" * 40
    with pytest.raises(ValueError, match="HEAD"):
        runner.verify_repository(ROOT, "1" * 40)
    values[("rev-parse", "HEAD")] = "1" * 40
    values[("rev-parse", "origin/main")] = "2" * 40
    with pytest.raises(ValueError, match="origin/main"):
        runner.verify_repository(ROOT, "1" * 40)
    values[("rev-parse", "origin/main")] = "1" * 40
    values[("status", "--porcelain=v1", "--untracked-files=no")] = " M tracked"
    with pytest.raises(ValueError, match="clean"):
        runner.verify_repository(ROOT, "1" * 40)


def test_non_dry_execution_requires_separate_authorization(monkeypatch) -> None:
    runner = module()
    monkeypatch.setattr(
        sys,
        "argv",
        [
            str(SCRIPT),
            "--repository-root",
            str(ROOT),
            "--repository-commit",
            "1" * 40,
            "--run-id",
            RUN_ID,
        ],
    )
    monkeypatch.setattr(runner, "verify_repository", lambda *_args: None)
    with pytest.raises(SystemExit, match="authorization is required"):
        runner.main()


@pytest.mark.asyncio
async def test_complete_148_utterance_execution_uses_gateway_only(tmp_path) -> None:
    runner, policy = policy_value()
    adapters = runner.RecordingProviderAdapters(ROOT)
    identity = runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64)
    result, ledger, budget = await runner.execute_run(
        root=ROOT,
        policy=policy,
        run_id=RUN_ID,
        identity=identity,
        run_dir=tmp_path / RUN_ID,
        adapters=adapters,
        resume=False,
        lightweight_corpus=True,
    )
    assert len(result["variant_results"]) == 148
    assert (
        sum(
            item["required_evaluation_route"] == "deterministic"
            for item in result["variant_results"]
        )
        == 42
    )
    assert Counter(stage for stage, _ in adapters.calls) == {
        "corpus_embedding": 8,
        "query_embedding": 1,
        "reranker": 140,
        "generation": 86,
        "judge": 86,
    }
    assert len(adapters.calls) == 321
    reranker_payloads = [
        payload for stage, payload in adapters.payloads if stage == "reranker"
    ]
    assert len(reranker_payloads) == 140
    assert {
        candidate["side"]
        for payload in reranker_payloads
        for candidate in payload["candidates"]
    } == {"primary", "comparison"}
    generation_payloads = [
        payload for stage, payload in adapters.payloads if stage == "generation"
    ]
    assert len(generation_payloads) == 86
    assert {
        evidence["side"]
        for payload in generation_payloads
        for evidence in payload["evidence"]
    } == {"primary", "comparison"}
    assert {
        side
        for payload in generation_payloads
        for side in payload["constraints"]["required_sides"]
    } == {"primary", "comparison"}
    judge_payloads = [
        payload for stage, payload in adapters.payloads if stage == "judge"
    ]
    assert len(judge_payloads) == 86
    assert {
        evidence["side"]
        for payload in judge_payloads
        for evidence in payload["retrieved_evidence"]
    } == {"PRIMARY", "COMPARISON"}
    execution = json.loads((ledger.run_dir / "execution-observations.json").read_text())
    assert {
        evidence["side"]
        for observation in execution["observations"]
        for evidence in observation.get("selected_evidence", [])
    } == {"PRIMARY", "COMPARISON"}
    assert [item["items"] for item in execution["corpus_embedding_batches"]] == [
        128,
        128,
        128,
        128,
        128,
        128,
        128,
        104,
    ]
    corpus_payloads = [
        payload for stage, payload in adapters.payloads if stage == "corpus_embedding"
    ]
    assert [
        source_id
        for payload in corpus_payloads
        for source_id in [item["source_id"] for item in payload["items"]]
    ] == [
        str(runner.uuid5(runner.NAMESPACE_URL, f"proof:{index}"))
        for index in range(1000)
    ]
    assert budget.actual_attempts == 321
    assert len(list(ledger.responses_dir.glob("*.json"))) == 321
    assert (ledger.run_dir / "execution-observations.json").is_file()
    assert (ledger.run_dir / "answer-judgements.json").is_file()
    assert result["execution_observations_sha256"] == runner.sha256(
        ledger.run_dir / "execution-observations.json"
    )

    resumed_adapters = runner.RecordingProviderAdapters(ROOT)
    resumed, _, resumed_budget = await runner.execute_run(
        root=ROOT,
        policy=policy,
        run_id=RUN_ID,
        identity=identity,
        run_dir=tmp_path / RUN_ID,
        adapters=resumed_adapters,
        resume=True,
        lightweight_corpus=True,
    )
    assert resumed == result
    assert resumed_adapters.calls == []
    assert resumed_budget.actual_attempts == 321


@pytest.mark.asyncio
async def test_partial_corpus_batch_failure_stops_before_later_batches(
    tmp_path,
) -> None:
    runner, policy = policy_value()

    class FourthBatchFailure(runner.RecordingProviderAdapters):  # type: ignore[name-defined]
        def __init__(self):
            super().__init__(ROOT)
            self.corpus_calls = 0

        def corpus_embedding(self, payload):
            self.corpus_calls += 1
            if self.corpus_calls == 4:
                raise ValueError("deliberate batch failure")
            return super().corpus_embedding(payload)

    adapters = FourthBatchFailure()
    identity = runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64)
    ledger = runner.AppendOnlyRunLedger(tmp_path / RUN_ID, identity, create=True)
    budget = runner.HardBudget(policy["ceilings"], monotonic=lambda: 0.0)
    gate = runner.BudgetedDispatchGateway(policy, budget, ledger)
    with pytest.raises(ValueError, match="deliberate batch failure"):
        await runner.LiveExecutionEngine(ROOT, policy, RUN_ID, gate, adapters).execute(
            lightweight_corpus=True
        )
    corpus_events = [
        event
        for event in ledger.events
        if event.get("request", {}).get("stage") == "corpus_embedding"
        or (
            event.get("request_id")
            and event.get("request_id")
            in {
                runner.request_id(
                    RUN_ID, "corpus_embedding", f"frozen-primary-corpus:batch-{n:04d}"
                )
                for n in range(1, 9)
            }
        )
    ]
    assert adapters.corpus_calls == 4
    assert (
        sum(event["event_type"] == "request_completed" for event in corpus_events) == 3
    )
    assert not any(
        event.get("request_id")
        == runner.request_id(
            RUN_ID, "corpus_embedding", "frozen-primary-corpus:batch-0005"
        )
        for event in ledger.events
    )


def test_retry_authority_is_isolated_to_one_corpus_batch(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    payload = runner.embedding_payload(
        runner.corpus_batches(lightweight_chunks(runner))[3],
        purpose="document",
        workspace_id=runner.uuid5(runner.NAMESPACE_URL, "workspace"),
        correlation_subject="frozen-primary-corpus:batch-0004",
    )
    item = runner.dispatch_request_for(
        policy,
        RUN_ID,
        "corpus_embedding",
        "frozen-primary-corpus:batch-0004",
        payload,
    )
    calls = 0

    def provider():
        nonlocal calls
        calls += 1
        if calls == 1:
            raise runner.RetryableDispatchError(
                "rate_limited", provider_status=429, provider_error_code="rate_limited"
            )
        value = {
            "source_ids": [entry["source_id"] for entry in payload["items"]],
            "vectors": [[1.0] + [0.0] * 1023] * len(payload["items"]),
        }
        return runner.ProviderResult(
            value,
            runner.DispatchReceipt(
                response_digest=runner.digest_value(value),
                input_tokens=1,
                output_tokens=0,
            ),
        )

    result = gate.corpus_embedding(item, provider)
    assert calls == 2
    assert len(runner._vectors(payload, result.value)) == 128
    failed = next(
        event for event in ledger.events if event["event_type"] == "attempt_failed"
    )
    assert failed["provider_status"] == 429
    assert failed["provider_error_code"] == "rate_limited"


@pytest.mark.asyncio
async def test_no_selected_evidence_is_preserved_without_generation_or_judging(
    tmp_path,
) -> None:
    runner, policy = policy_value()

    class EmptyRerankerAdapters(
        runner.RecordingProviderAdapters  # type: ignore[name-defined]
    ):
        def rerank(self, payload):
            result = super().rerank(payload)
            value = {"candidates": []}
            return runner.ProviderResult(
                value,
                runner.DispatchReceipt(
                    response_digest=runner.digest_value(value),
                    input_tokens=result.receipt.input_tokens,
                    output_tokens=result.receipt.output_tokens,
                    provider_request_id_digest=(
                        result.receipt.provider_request_id_digest
                    ),
                ),
            )

    adapters = EmptyRerankerAdapters(ROOT)
    identity = runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64)
    ledger = runner.AppendOnlyRunLedger(tmp_path / RUN_ID, identity, create=True)
    budget = runner.HardBudget(policy["ceilings"], monotonic=lambda: 0.0)
    gate = runner.BudgetedDispatchGateway(policy, budget, ledger)
    execution = await runner.LiveExecutionEngine(
        ROOT, policy, RUN_ID, gate, adapters
    ).execute(lightweight_corpus=True)
    full = [
        item
        for item in execution["observations"]
        if item["required_evaluation_route"] == "full"
    ]
    assert len(full) == 86
    assert all(item["actual_outcome"] == "INSUFFICIENT_EVIDENCE" for item in full)
    assert all("generation" not in item for item in full)
    assert all(
        item["generation_suppressed_reason"] == "no_threshold_qualified_evidence"
        for item in full
    )
    execution_path = ledger.run_dir / "execution-observations.json"
    execution_sha256 = runner.write_immutable_json(execution_path, execution)
    judging = await runner.execute_answer_judging(
        root=ROOT,
        policy=policy,
        run_id=RUN_ID,
        execution_path=execution_path,
        execution_sha256=execution_sha256,
        gateway=gate,
        adapters=adapters,
    )
    assert judging["judgements"] == []
    judging_path = ledger.run_dir / "answer-judgements.json"
    runner.write_immutable_json(judging_path, judging)
    scored = runner.score_frozen_execution(
        root=ROOT,
        policy=policy,
        execution_path=execution_path,
        judging_path=judging_path,
    )
    assert scored["system_correctness"]["outcome_accuracy"] < 1.0
    assert scored["preliminary_pilot_readiness"] == "NOT_PILOT_READY"
    assert Counter(stage for stage, _ in adapters.calls) == {
        "corpus_embedding": 8,
        "query_embedding": 1,
        "reranker": 140,
    }


def test_adapter_retry_proof_fails_closed() -> None:
    runner = module()
    adapters = runner.RecordingProviderAdapters(ROOT)
    adapters.internal_attempts = 2
    with pytest.raises(RuntimeError, match="internal_retries"):
        adapters.assert_internal_retries_disabled()


def test_completed_response_tampering_blocks_resume(tmp_path) -> None:
    runner, policy = policy_value()
    gate, ledger = gateway(tmp_path, runner, policy)
    item = request(runner, policy)
    gate.generate(item, lambda: receipt(runner, outputs=1))
    response_path = ledger.responses_dir / f"{item.request_id}.json"
    response_path.write_text("{}")
    with pytest.raises(ValueError, match="modified"):
        runner.BudgetedDispatchGateway(
            policy,
            runner.HardBudget(policy["ceilings"], monotonic=lambda: 0.0),
            ledger,
            resume=True,
        ).generate(item, lambda: receipt(runner))


async def execute_with_population(tmp_path, source_population):
    runner, policy = policy_value()
    root = tmp_path / runner.digest_value(source_population)
    root.mkdir()
    (root / "population.json").write_text(json.dumps(source_population))
    policy = copy.deepcopy(policy)
    policy["population_path"] = "population.json"
    adapters = runner.RecordingProviderAdapters(ROOT)
    identity = runner.run_identity(policy, RUN_ID, POLICY, "1" * 40, "2" * 64)
    ledger = runner.AppendOnlyRunLedger(root / "run", identity, create=True)
    budget = runner.HardBudget(policy["ceilings"], monotonic=lambda: 0.0)
    gate = runner.BudgetedDispatchGateway(policy, budget, ledger)
    execution = await runner.LiveExecutionEngine(
        root, policy, RUN_ID, gate, adapters
    ).execute(lightweight_corpus=True)
    return runner, execution, adapters


def synthetic_scoring_artifacts(tmp_path):
    tmp_path.mkdir(parents=True, exist_ok=True)
    runner, policy = policy_value()
    population = json.loads((ROOT / policy["population_path"]).read_text())
    route_by_case = {
        case_id: route
        for route, case_ids in policy["required_evaluation_routes"].items()
        for case_id in case_ids
    }
    observations = []
    judgements = []
    for case in population["cases"]:
        route = route_by_case[case["case_id"]]
        for variant in case["variants"]:
            selected = []
            funnel = {}
            catalog = {}
            expected_units = (
                case["expected_evidence"] if route != "deterministic" else []
            )
            for index, unit in enumerate(expected_units, 1):
                chunk_id = f"chunk-{case['case_id']}-{variant['variant_id']}-{index}"
                historical = bool(
                    unit["side"] == "COMPARISON"
                    or case["context"]["temporal_mode"] == "HISTORICAL_REFERENCE"
                )
                item = {
                    "chunk_id": chunk_id,
                    "document_id": f"document-{index}",
                    "document_family_id": f"family-{index}",
                    "side": unit["side"],
                    "text": unit["quotation"],
                    "source_sha256": unit["source_sha256"],
                    "tenant": "primary",
                    "governance_status": "withdrawn" if historical else "approved",
                    "effective_date": "2000-01-01",
                    "superseded_date": "2023-01-01" if historical else None,
                    "applicability_scope": "universal",
                    "applicability_locations": [],
                }
                selected.append(item)
                catalog[chunk_id] = item
                side = funnel.setdefault(unit["side"], {})
                for stage in (
                    "eligible",
                    "dense",
                    "sparse",
                    "union",
                    "fusion",
                    "reranker",
                    "threshold",
                    "final",
                ):
                    side.setdefault(f"{stage}_chunk_ids", []).append(chunk_id)
            observation = {
                "case_id": case["case_id"],
                "variant_id": variant["variant_id"],
                "required_evaluation_route": route,
                "actual_outcome": case["expected_outcome"]["retrieval"],
                "frozen_plan": case["context"],
                "selected_chunk_ids": [item["chunk_id"] for item in selected],
                "selected_evidence": selected,
                "candidate_funnel": funnel,
                "candidate_catalog": catalog,
            }
            if route == "full":
                observation["generation"] = {
                    "outcome": "answered",
                    "answer_parts": [
                        {
                            "text": "Supported answer.",
                            "evidence_ids": [
                                f"ev-{index:02d}"
                                for index in range(1, len(selected) + 1)
                            ],
                        }
                    ],
                    "unsupported_aspects": [],
                }
                judgements.append(
                    {
                        "case_id": case["case_id"],
                        "variant_id": variant["variant_id"],
                        "result": {
                            "scores": {
                                "ANSWER_PART_GROUNDEDNESS": 1.0,
                                "ANSWER_FACTUAL_PRECISION": 1.0,
                                "ANSWER_COMPLETENESS": 1.0,
                            }
                        },
                    }
                )
            observations.append(observation)
    execution = {
        "schema_version": "r28-s04-execution-observations-v1",
        "run_id": RUN_ID,
        "utterances": 148,
        "actual_deterministic_terminations": 62,
        "observations": observations,
    }
    execution_path = tmp_path / "execution-observations.json"
    runner.write_immutable_json(execution_path, execution)
    judging = {
        "schema_version": "r28-s04-answer-judgements-v1",
        "run_id": RUN_ID,
        "execution_observations_sha256": runner.sha256(execution_path),
        "judgements": judgements,
    }
    judging_path = tmp_path / "answer-judgements.json"
    runner.write_immutable_json(judging_path, judging)
    return runner, policy, execution_path, judging_path


def test_execution_projection_contains_no_oracle_fields() -> None:
    runner, policy = policy_value()
    population = json.loads((ROOT / policy["population_path"]).read_text())
    projection = runner.execution_projection(population, policy)
    forbidden = {
        "expected_outcome",
        "expected_evidence",
        "expected_answer",
        "slices",
        "safety_verdict",
    }
    assert not forbidden.intersection(str(projection))
    assert len(projection["cases"]) == 74


@pytest.mark.asyncio
async def test_expected_label_mutations_do_not_change_execution(tmp_path) -> None:
    population = json.loads(
        POLICY.parent.parent.parent.joinpath(
            "engineering-populations/dolved-care-v4/v2/population.json"
        ).read_text()
    )
    changed = copy.deepcopy(population)
    changed["cases"][0]["expected_outcome"]["retrieval"] = "INSUFFICIENT_EVIDENCE"
    changed["cases"][0]["expected_evidence"][0]["quotation"] = "wrong oracle"
    changed["cases"][0]["expected_evidence"][0]["source_sha256"] = "0" * 64
    _, first, first_adapters = await execute_with_population(tmp_path, population)
    _, second, second_adapters = await execute_with_population(tmp_path, changed)
    assert first == second
    assert first_adapters.calls == second_adapters.calls


@pytest.mark.asyncio
async def test_execution_completes_when_expectations_are_unavailable(tmp_path) -> None:
    population = json.loads(
        (ROOT / json.loads(POLICY.read_text())["population_path"]).read_text()
    )
    for case in population["cases"]:
        case.pop("expected_outcome")
        case.pop("expected_evidence")
        case.pop("slices")
    _, execution, adapters = await execute_with_population(tmp_path, population)
    assert len(execution["observations"]) == 148
    assert Counter(stage for stage, _ in adapters.calls) == {
        "corpus_embedding": 8,
        "query_embedding": 1,
        "reranker": 140,
        "generation": 86,
    }


def test_scorer_requires_immutable_files_and_has_no_dispatch_dependency(
    tmp_path,
) -> None:
    runner, policy = policy_value()
    with pytest.raises(ValueError, match="immutable execution"):
        runner.score_frozen_execution(
            root=ROOT,
            policy=policy,
            execution_path=tmp_path / "missing-execution.json",
            judging_path=tmp_path / "missing-judging.json",
        )
    parameters = inspect.signature(runner.score_frozen_execution).parameters
    assert "gateway" not in parameters
    assert "adapters" not in parameters


def test_passing_synthetic_observations_meet_frozen_rules(tmp_path) -> None:
    runner, policy, execution_path, judging_path = synthetic_scoring_artifacts(tmp_path)
    result = runner.score_frozen_execution(
        root=ROOT,
        policy=policy,
        execution_path=execution_path,
        judging_path=judging_path,
    )
    assert result["absolute_failure_count"] == 0
    assert all(result["threshold_results"].values())
    assert result["preliminary_pilot_readiness"] == "PILOT_READY"
    assert len(result["variant_results"]) == 148
    assert result["per_slice"]
    assert all(
        value["covered_evidence_units"] == value["expected_evidence_units"]
        for value in result["retrieval_funnel"].values()
    )


def test_expected_evidence_mutation_changes_scoring_not_execution(tmp_path) -> None:
    runner, policy, execution_path, judging_path = synthetic_scoring_artifacts(tmp_path)
    before_execution = execution_path.read_bytes()
    before = runner.score_frozen_execution(
        root=ROOT,
        policy=policy,
        execution_path=execution_path,
        judging_path=judging_path,
    )
    population = json.loads((ROOT / policy["population_path"]).read_text())
    population["cases"][0]["expected_evidence"][0]["quotation"] = "not present"
    population["cases"][0]["expected_evidence"][0]["source_sha256"] = "0" * 64
    changed_population = tmp_path / "changed-population.json"
    changed_population.write_text(json.dumps(population))
    changed_policy = copy.deepcopy(policy)
    changed_policy["population_path"] = str(changed_population)
    after = runner.score_frozen_execution(
        root=ROOT,
        policy=changed_policy,
        execution_path=execution_path,
        judging_path=judging_path,
    )
    assert execution_path.read_bytes() == before_execution
    assert (
        after["retrieval_quality"]["recall_at_5"]
        < before["retrieval_quality"]["recall_at_5"]
    )


def test_system_correctness_metrics_inspect_recorded_plan_and_evidence(
    tmp_path,
) -> None:
    runner, policy, execution_path, judging_path = synthetic_scoring_artifacts(tmp_path)
    execution = json.loads(execution_path.read_text())
    population = json.loads((ROOT / policy["population_path"]).read_text())
    by_case = {case["case_id"]: case for case in population["cases"]}
    temporal_results = [
        item
        for item in execution["observations"]
        if item["required_evaluation_route"] == "full"
        and any(
            value.startswith("temporal.")
            for value in by_case[item["case_id"]]["slices"]
        )
        and item["selected_evidence"]
    ][:4]
    assert len(temporal_results) == 4
    for temporal in temporal_results:
        incorrect_source = temporal["selected_evidence"][0]["source_sha256"]
        for item in temporal["selected_evidence"]:
            if item["source_sha256"] == incorrect_source:
                item["source_sha256"] = "0" * 64
                item["governance_status"] = "draft"
    eligibility = next(
        item
        for item in execution["observations"]
        if item["selected_evidence"]
        and (
            "safety.cross_tenant" in by_case[item["case_id"]]["slices"]
            or any(
                value.startswith("applicability.")
                for value in by_case[item["case_id"]]["slices"]
            )
        )
    )
    eligibility["selected_evidence"][0]["tenant"] = "foreign_tenant"
    for item in execution["observations"][:4]:
        item["frozen_plan"] = {"temporal_mode": "WRONG"}
    execution_path.unlink()
    judging = json.loads(judging_path.read_text())
    judging_path.unlink()
    runner.write_immutable_json(execution_path, execution)
    judging["execution_observations_sha256"] = runner.sha256(execution_path)
    runner.write_immutable_json(judging_path, judging)
    result = runner.score_frozen_execution(
        root=ROOT,
        policy=policy,
        execution_path=execution_path,
        judging_path=judging_path,
    )
    assert result["system_correctness"]["planner_intent_input_integrity"] < 0.98
    assert result["system_correctness"]["eligibility_accuracy"] < 1.0
    assert result["system_correctness"]["temporal_authority_accuracy"] < 1.0
    assert result["system_correctness"]["version_selection_accuracy"] < 1.0
    assert result["threshold_results"]["planner_intent_input_integrity"] is False
    assert result["threshold_results"]["version_selection_accuracy"] is False


def test_failing_synthetic_observations_expose_absolute_and_slice_failures(
    tmp_path,
) -> None:
    runner, policy, execution_path, judging_path = synthetic_scoring_artifacts(tmp_path)
    execution = json.loads(execution_path.read_text())
    population = json.loads((ROOT / policy["population_path"]).read_text())
    by_case = {case["case_id"]: case for case in population["cases"]}
    full = next(
        item
        for item in execution["observations"]
        if item["required_evaluation_route"] == "full"
    )
    full["actual_outcome"] = "INSUFFICIENT_EVIDENCE"
    full["selected_evidence"][0]["tenant"] = "foreign_tenant"
    full["selected_evidence"][0]["governance_status"] = "draft"
    full["generation"]["answer_parts"][0]["evidence_ids"].append("fabricated")
    comparison = next(
        item
        for item in execution["observations"]
        if by_case[item["case_id"]]["context"]["temporal_mode"] == "COMPARE"
        and item.get("selected_evidence")
    )
    comparison["selected_evidence"][0]["side"] = "WRONG"
    injection = next(
        item
        for item in execution["observations"]
        if "safety.prompt_injection" in by_case[item["case_id"]]["slices"]
        and item.get("generation")
    )
    injection["selected_evidence"][0]["tenant"] = "prompt_injection"
    judging = json.loads(judging_path.read_text())
    target = next(
        item
        for item in judging["judgements"]
        if (item["case_id"], item["variant_id"])
        == (injection["case_id"], injection["variant_id"])
    )
    target["result"]["scores"]["ANSWER_PART_GROUNDEDNESS"] = 0.0
    execution_path.unlink()
    judging_path.unlink()
    runner.write_immutable_json(execution_path, execution)
    judging["execution_observations_sha256"] = runner.sha256(execution_path)
    runner.write_immutable_json(judging_path, judging)
    result = runner.score_frozen_execution(
        root=ROOT,
        policy=policy,
        execution_path=execution_path,
        judging_path=judging_path,
    )
    failures = {item["failure"] for item in result["absolute_failures"]}
    assert {
        "cross_tenant_leakage",
        "ineligible_evidence",
        "fabricated_citation",
        "comparison_side_conflation",
        "unsafe_prompt_injection_compliance",
        "unsupported_material_claim",
    } <= failures
    assert result["preliminary_pilot_readiness"] == "NOT_PILOT_READY"
    assert any(
        value["absolute_failure_count"] for value in result["per_slice"].values()
    )


def test_wrong_deterministic_outcome_reduces_outcome_accuracy(tmp_path) -> None:
    runner, policy, execution_path, judging_path = synthetic_scoring_artifacts(tmp_path)
    execution = json.loads(execution_path.read_text())
    deterministic = next(
        item
        for item in execution["observations"]
        if item["required_evaluation_route"] == "deterministic"
    )
    deterministic["actual_outcome"] = "INSUFFICIENT_EVIDENCE"
    execution_path.unlink()
    judging_path.unlink()
    runner.write_immutable_json(execution_path, execution)
    # Re-bind the original judgement payload after changing execution bytes.
    fresh_judging_path = synthetic_scoring_artifacts(tmp_path / "fresh")[3]
    original_judging = json.loads(fresh_judging_path.read_text())
    original_judging["execution_observations_sha256"] = runner.sha256(execution_path)
    runner.write_immutable_json(judging_path, original_judging)
    result = runner.score_frozen_execution(
        root=ROOT,
        policy=policy,
        execution_path=execution_path,
        judging_path=judging_path,
    )
    assert result["system_correctness"]["outcome_accuracy"] < 1.0
