# R28-S04 R01 — bounded review amendments

2026-09-06. Status: proposed corrections prepared; acceptance pending. Code HEAD remains `f1c7e69f3798558ddeef3e1f43aa6df2d0dacc9a`.

The [independent review](r28-s04-r01-reviewed.md) reported three material specification gaps. This pass changes only proposal/review documentation, not production behaviour or accepted ADRs.

1. **Transition semantics:** recommend the review's option (c), an explicit narrow amendment to ADR-0022 Decision 3 for transition-period/boundary comparisons. Keep the actual adjacent pair even if both states are historical; never substitute today's current version. Added exact side semantics and a March/June/September regression requirement. This preserves the intended user question instead of silently answering a different comparison. David's acceptance is still required.
2. **Supporting edge:** define it as descriptive diagnostic metadata only in this slice. It cannot gate the support need, alter budgets or be passed as a generation instruction. Added an execution-equivalence regression with and without the edge.
3. **Aspect status:** remove independently model-generated status/content. The provider supplies references to existing authoritative answer/gap fields; Laravel derives status and rejects reference/outcome contradictions. Added exact derivation table, all-need/reference coverage, invalid-index/unknown-field checks and valid/invalid fixture requirements. Semantic support is not falsely presented as deterministically provable.

Changed proposal locations: `minimum-slice-contract.md` sections 1–3 and ADR-0038 Decision. The plan schema and six existing interpretation examples retain their shapes and values. No thresholds, providers, frozen inputs, runtime code or index changed.

The pre-existing `ClaimDocumentGovernanceEmailEnvelope 2.php` was already present in Codex's R00 starting inventory; it is unrelated and untouched, not newly introduced drift. No unrelated duplicate/cache cleanup was performed.

Validation: bounded consistency review against ADR-0022 Decision 3 and ADR-0023's generation ownership/outcome invariants; interpretation schema/examples unchanged; whitespace checks. These are document checks and specified future regression fixtures, not executed production tests or an independent passing verdict.

Next: accept/review these three proposed amendments under the existing R01 gate, then R01P. Do not infer acceptance or start a general audit, implementation or live evaluation from this record. No commit, push, provider or AWS call.
