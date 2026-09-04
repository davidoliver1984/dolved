import copy
import importlib.util
import inspect
import json
import sys
from collections import Counter
from pathlib import Path

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
        "corpus_embedding": 1,
        "query_embedding": 1,
        "reranker": 140,
        "generation": 86,
        "judge": 86,
    }
    assert result["budget"] | {"provider_calls": result["provider_calls"]} == {
        "base_provider_requests": 314,
        "physical_attempts": 628,
        "input_tokens": 7_416_320,
        "output_tokens": 1_056_768,
        "maximum_planned_cost_usd": "3.30086400",
        "provider_calls": 0,
    }


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
    adapters = runner.RecordingProviderAdapters()
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
        "corpus_embedding": 1,
        "query_embedding": 1,
        "reranker": 140,
        "generation": 86,
        "judge": 86,
    }
    assert budget.actual_attempts == 314
    assert len(list(ledger.responses_dir.glob("*.json"))) == 314
    assert (ledger.run_dir / "execution-observations.json").is_file()
    assert (ledger.run_dir / "answer-judgements.json").is_file()
    assert result["execution_observations_sha256"] == runner.sha256(
        ledger.run_dir / "execution-observations.json"
    )

    resumed_adapters = runner.RecordingProviderAdapters()
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
    assert resumed_budget.actual_attempts == 314


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

    adapters = EmptyRerankerAdapters()
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
        "corpus_embedding": 1,
        "query_embedding": 1,
        "reranker": 140,
    }


def test_adapter_retry_proof_fails_closed() -> None:
    runner = module()
    adapters = runner.RecordingProviderAdapters()
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
    adapters = runner.RecordingProviderAdapters()
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
        "corpus_embedding": 1,
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
