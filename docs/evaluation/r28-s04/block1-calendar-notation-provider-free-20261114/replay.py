"""Provider-free replay of retained temporal fields, with explicit projection limits."""
import json
import sys
from copy import deepcopy
from pathlib import Path

from app.retrieval.needs import EvidenceNeedsPlan, FactReference, NeedAuthority, UserTurn
from app.retrieval.needs_planner import (
    NeedsValidationFailure,
    _canonicalize_explicit_calendar_values,
)

repo = Path(sys.argv[1])
root = Path(__file__).resolve().parent
diagnostic = root.parent / "block1-planner-contract-diagnostic-20261114/live"
rows = []
for item in ("G2", "G3"):
    data = json.loads((diagnostic / f"cases/{item}.json").read_text())
    text = json.loads((diagnostic / f"inputs/{item}.json").read_text())["utterance"]
    turns = (UserTurn(turn_id="user-1", text=text),)
    for attempt in data["attempts"]:
        original = attempt["proposed_plan"]
        converted, count = _canonicalize_explicit_calendar_values(original, turns)
        row = {"item": item, "attempt": attempt["attempt"], "normalizations": count}
        row["changes"] = []
        for i, (old, new) in enumerate(zip(original["needs"], converted["needs"])):
            for field in ("authority", "fact_reference"):
                if old[field] != new[field]:
                    row["changes"].append({"path": f"needs.{i}.{field}.value",
                        "old": old[field]["value"], "new": new[field]["value"]})
            # 'kind:null' was added by the observer, not part of FactReference.
            fact = {k: v for k, v in new["fact_reference"].items() if k != "kind"}
            FactReference.model_validate(fact)
            NeedAuthority.model_validate(new["authority"])
        row["retained_temporal_fields_schema"] = "PASS"

        # The observer did NOT retain a complete plan. These are harness fillers,
        # not reconstructed model output. They allow the real grounding method
        # to inspect exactly the retained spans/temporal values/relationships.
        envelope = deepcopy(converted)
        envelope["clarification"] = None
        for need in envelope["needs"]:
            need.update(central=True, supports_need_id=None)
            if need["fact_reference"] is not None:
                need["fact_reference"].pop("kind", None)
        plan = EvidenceNeedsPlan.model_validate(envelope)
        try:
            plan.validate_grounding(turns, require_order=False, evaluated_at=data["started_at"])
            plan.validate_catalogue((), turns)
            plan.execution_plan()
            row["synthetic_envelope_grounding"] = "PASS"
        except ValueError as error:
            row["synthetic_envelope_grounding"] = NeedsValidationFailure("grounding", error).validation
        rows.append(row)

result = {
    "scope": "retained temporal fields plus synthetic-envelope grounding; not full original-plan replay",
    "missing_from_capture": ["clarification", "central", "supports_need_id", "claim_under_test", "scope_question"],
    "harness_fillers": {"clarification": None, "central": True, "supports_need_id": None},
    "optional_contract_defaults": {"claim_under_test": None, "scope_question": None},
    "projection_only_field_removed": "fact_reference.kind",
    "interpretation_checked": False, "provider_calls": 0, "rows": rows,
}
(root / "replay.json").write_text(json.dumps(result, indent=2) + "\n")
print(json.dumps(result, indent=2))
