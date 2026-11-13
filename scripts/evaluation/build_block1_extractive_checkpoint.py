"""Freeze the approved extractive checkpoint before any repaired output exists."""
import hashlib
import json
from pathlib import Path
from statistics import fmean

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs/evaluation/r28-s04/block1-extractive-checkpoint-20261113"
CORPUS = ROOT / "docs/evaluation/r28-s04/r07-evidence-units-v4-20261111/evidence-units-corpus.json"
BASELINE = ROOT / "docs/evaluation/r28-s04/r07-evidence-unit-metrics-v4-20261111/results-a.json"
SELECTED = "b01-02 b01-04 b01-05 b01-06 b01-07 b01-08 b01-09 b01-10 b02-01 b02-03 b02-06 b02-07 b02-08 b02-09 b03-01 b03-07 b03-10 b04-05 b04-07 b04-09 b05-01 b05-03 b05-05 b05-07 b05-09 b06-04 b06-05 b06-06 b06-11 b06-12".split()
DEVELOPMENT = ["b01-01", "b03-05"]


def main():
    OUT.mkdir(exist_ok=True)
    corpus = json.loads(CORPUS.read_text())
    cases = {c["case_id"]: c for c in corpus["cases"]}
    baseline = json.loads(BASELINE.read_text())
    items, scopes, selection = [], [], []
    for short in SELECTED + DEVELOPMENT:
        identity = f"v4.case.corrected-{short}"
        case = cases[identity]
        assert case["evidence_units"]
        variant = next(v for v in case["variants"] if v["variant_id"] == "v1")
        items.append({"case_id": identity, "variant_id": "v1", "utterance": variant["question"]})
        scope = "security_test" if "scope.security_test" in case["slices"] else "primary"
        scopes.append({"case_id": identity, "variant_id": "v1", "scope": scope})
        previous = next(v for v in baseline["variants"] if v["case_id"] == identity and v["variant_id"] == "v1")
        selection.append({"case_id": identity, "variant_id": "v1", "role": "development" if short in DEVELOPMENT else "labelled_checkpoint", "slices": case["slices"], "baseline": previous})
    subset = "R28-BLOCK1-EXTRACTIVE-THIRTY-PLUS-TWO-20261113-V1"
    aggregates = {}
    for side in ["PRIMARY", "COMPARISON"]:
        values = [s["baseline"]["attempted_rerank"][side]["recall_at_k"] for s in selection[:30] if s["baseline"]["reached_rerank"] and s["baseline"].get("attempted_rerank", {}).get(side) is not None]
        aggregates[side] = {"attempted_variant_count": len(values), "recall_at_5": fmean(values) if values else None}
    outputs = {
        "execution-input.json": {"subset_id": subset, "items": items},
        "execution-scopes.json": {"manifest_id": subset, "items": scopes},
        "selection-and-expectations.json": {"subset_id": subset, "K": 5, "reservation_usd": 3,
            "selection_basis": "Thirty distinct labelled cases, v1, stratified across all six batches: comparison, temporal, multi-part, local/inherited scope, typo/alias, security, records. Development questions excluded from ranking gate. No fresh output inspected. Cross-turn anaphora and explicit user examples are covered provider-free; this single-turn population is not claimed to exercise them live.",
            "acceptance": ["No executed query or coverage label contains wording absent from user words and validated typed fields", "Attempted-search Recall@5 loses at most 0.03 on each side versus the same selected variants", "No new interpretation-failure or clarification on clear questions", "No new failures beyond 38 AI / 2 Laravel"],
            "baseline_aggregates": aggregates, "cases": selection,
            "source_sha256": {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in (CORPUS, BASELINE)}},
    }
    for name, value in outputs.items():
        with (OUT / name).open("x") as handle:
            handle.write(json.dumps(value, indent=2) + "\n")
    (OUT / "selection-checksums.sha256").write_text("".join(f"{hashlib.sha256((OUT / name).read_bytes()).hexdigest()}  {name}\n" for name in sorted(outputs)))
    print(json.dumps({"selection": len(items), "baseline": aggregates}))


if __name__ == "__main__":
    main()
