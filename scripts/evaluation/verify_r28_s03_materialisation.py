import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
RUN = ROOT / "docs/evaluation/r28-s03/run"


def load_json(path: Path) -> dict[str, object]:
    value = json.loads(path.read_text())
    if not isinstance(value, dict):
        raise TypeError(f"{path} must contain a JSON object")
    return value


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main() -> None:
    result = load_json(RUN / "materialisation-result.json")
    evidence = load_json(RUN / "verification-evidence.json")

    assert result["run_id"] == evidence["run_id"]
    assert result["repository_commit"] == evidence["execution_commit"]
    assert result["provider_calls"] == evidence["provider_calls"] == 0
    assert result["aws_calls"] == evidence["aws_calls"] == 0
    assert evidence["selective_reruns"] == 0

    scopes = result["scopes"]
    assert isinstance(scopes, dict)
    expected = {
        "primary": (300, 185, 300),
        "foreign_tenant": (12, 12, 12),
        "prompt_injection_pack": (6, 6, 6),
    }
    workspaces: set[str] = set()
    for name, (documents, families, indexed) in expected.items():
        scope = scopes[name]
        assert isinstance(scope, dict)
        assert scope["realised_documents"] == documents
        assert scope["realised_families"] == families
        assert scope["realised_indexed"] == indexed
        workspaces.add(str(scope["workspace_public_id"]))
    assert len(workspaces) == 3

    negative = result["negative_fixtures"]
    assert isinstance(negative, dict)
    assert negative["status"] == "complete"
    assert negative["expected_fixtures"] == negative["observed_fixtures"] == 13
    outcomes = negative["outcomes"]
    assert isinstance(outcomes, dict) and len(outcomes) == 13

    database = evidence["database"]
    assert isinstance(database, dict)
    assert database["documents"] == database["indexed_documents"] == 318
    assert database["canonical_chunks"] == 1000
    assert database["failed_jobs"] == database["pending_outbox_messages"] == 0

    queues = evidence["queues"]
    assert isinstance(queues, dict) and set(queues.values()) == {0}

    retrieval = evidence["retrieval"]
    assert isinstance(retrieval, dict)
    assert retrieval["collection_status"] == "green"
    assert retrieval["optimizer_status"] == "ok"
    assert retrieval["active_hybrid_points"] == 1000
    generations = retrieval["active_generations"]
    assert isinstance(generations, dict)
    for generation in generations.values():
        assert isinstance(generation, dict)
        count = generation["expected_points"]
        assert generation["assigned_chunks"] == count
        assert generation["dense_points"] == count
        assert generation["sparse_points"] == count

    checksum_lines = (RUN / "checksums.sha256").read_text().splitlines()
    governed = {path.name for path in RUN.iterdir() if path.name != "checksums.sha256"}
    recorded: set[str] = set()
    for line in checksum_lines:
        digest, relative = line.split("  ", 1)
        path = ROOT / relative
        assert path.is_file(), f"missing governed artefact: {relative}"
        assert sha256(path) == digest, f"checksum mismatch: {relative}"
        recorded.add(path.name)
    assert recorded == governed, "checksum inventory does not cover the run directory"

    print("R28_S03_MATERIALISATION_VERIFIED")


if __name__ == "__main__":
    main()
