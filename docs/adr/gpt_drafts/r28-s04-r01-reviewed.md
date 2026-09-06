# R28-S04 R01 — independent review received 2026-09-06

Reviewer: Claude, supplied by David. Historical verdict below is retained unchanged in meaning; it is not an acceptance record. Source: `/Users/davidoliver/.codex/attachments/358cae99-b289-4368-993a-a6114b3f1590/pasted-text.txt`. A final newline is normalised for this Markdown record.

---

+Repository state

HEAD = f1c7e69f3798558ddeef3e1f43aa6df2d0dacc9a — matches expected exactly. Branch main.

Unexpected drift, not reverted: one untracked file outside the disclosed R00/R01 file set: apps/api/app/Actions/Documents/ClaimDocumentGovernanceEmailEnvelope 2.php (mtime 2 Sep 14:49, 8,694 bytes) — a stale editor/Finder duplicate of the real, currently-tracked ClaimDocumentGovernanceEmailEnvelope.php (mtime 2 Sep 18:48, 11,141 bytes; diff shows it's an earlier, superseded revision). Same pattern as the pre-existing " 2"-suffixed files already in the untracked list from before this session (e.g. docs/evaluation/planner-experiments/README 2.md). It predates R00/R01, is not one of the "What Codex has done" deliverables, and appears to be inert leftover clutter rather than anything this proposal introduced. Flagging per instruction; not reverted.

The three disclosed tracked-file diffs (docs/adr/README.md, docs/evaluation/r28-s04/production-repair-plan.md, tasks.json) are exactly as small and as described: an ADR-0038 index row, a two-line status flip (R28-S04 → in_progress), and the plan document itself. All other untracked files match the pre-existing list from before this repair effort began. No unrelated file was touched.

Verdict

R28_R01_MINIMUM_SLICE_BLOCKED_PENDING_CORRECTION

The plan's own process discipline (fix-inputs-before-execution, standing-authority-with-disclosed-bounds, shape-vs-semantics separation, honest tracker reconciliation) is genuinely good, and most of the eight review areas check out. But three specific gaps mean an implementer would have to invent a material decision mid-coding, which is exactly what this review exists to catch.

Material blockers

1. The transition_period/transition_boundary comparison semantics contradict ADR-0022's accepted "PRIMARY is always CURRENT" decision, and the proposal never acknowledges the conflict.

- File/section: docs/evaluation/r28-s04/minimum-slice-contract.md §1, "Comparison uses current versus requested historical/date state, or the actual adjacent transition pair" and the transition_period/transition_boundary authority-kind rows ("form actual before/after pairs" / "resolve actual adjacent authority states"). Cross-reference: docs/adr/0022-…md §3 — "PRIMARY is always resolved at CURRENT for V1 — no evidence in hand supports comparing two non-current states."
- Concrete failure: a transition-boundary comparison resolves to whichever two versions are adjacent across the named transition — not to "current." If a family has had transitions in March, June and September and the user asks "what changed during June?", the accepted adjacent pair is May-version vs June-version. If today's current version is the September one, that pair is historical-vs-historical, which ADR-0022 explicitly rules out for V1. ADR-0038 itself claims to extend "ADR-0022 Decision 1–4" but never mentions Decision 3's PRIMARY-always-CURRENT clause, and ADR-0022's own four temporal modes have no slot for a non-current-anchored comparison at all — transition_period/transition_boundary are genuinely new modes this proposal introduces without reconciling them against the existing constraint.
- Smallest safe correction: add one explicit sentence to §1 (and to authority kinds' resolution column) stating one of: (a) a transition pair whose "after" side is not the current attained version falls back to CURRENT vs. the pre-transition side, and states which side was silently substituted; or (b) such a pair fails closed to clarification, consistent with ADR-0022's existing fail-closed default for cases it can't safely resolve; or (c) this is a deliberate, narrow amendment to ADR-0022 Decision 3, scoped only to these two new authority kinds, stated as such. Any of the three is a few sentences — this isn't a redesign, it's naming the decision that's currently missing.
- Why before R01P: R01P's own worked "mixed" example is a comparison-plus-current-support question, and the plan's target defect (the 0606-06-06 bug) is exactly a transition_period case. This isn't a hypothetical edge case R01P can defer — it's in the slice's first test input.

2. supports_need_id is a required, validated schema field with zero specified runtime meaning.

- File/section: minimum-slice-contract.md §1 ("A supporting edge references another need, never itself; cycles fail") and minimum-slice-plan.schema.json $defs.need.supports_need_id. It appears nowhere else in the document — not in §2's evidence-selection/allocation description, not in §3's generation/coverage description.
- Concrete failure: the field is structurally enforced (no self-reference, no cycles) but never consumed. The slice's own primary worked example (minimum-slice-examples.json, "mixed") sets n2.supports_need_id = "n1" even though the accompanying table row calls n2 "eligible independently" — an apparent tension between the data and the prose that the document doesn't resolve. An implementer has to invent what "supports" actually changes: budget priority, generation-time grouping, answer ordering, or nothing at all.
- Smallest safe correction: one sentence in §2 or §3 stating what supports_need_id causes the system to do differently (or explicitly stating it's descriptive/informational only for this slice, with no runtime effect yet).
- Why before R01P: it's a required field on every need object R01P will actually construct — not optional, not deferred to R02+.

3. aspect_results risks reintroducing the exact "two independently generated representations" pattern ADR-0023 explicitly rejected, with no specified reconciliation against answer_parts/unsupported_aspects.

- File/section: minimum-slice-contract.md §3 — "The existing single generation call... assesses each aspect as supported, partial or unsupported" (i.e., aspect_results is model-generated). Cross-reference: docs/adr/0023-…md, "answer_parts[]: the sole authoritative generated representation" — which explicitly rejects a separate claims[] array specifically because "two independently generated representations of the same factual content create correlated state that deterministic application code cannot enforce."
- Concrete failure: nothing in the proposal says what happens if the model's aspect_results claims n1 is supported while unsupported_aspects still lists n1's aspect string (or vice versa). Without a stated deterministic cross-check, this is the same failure shape ADR-0023 designed against, just with a new field name.
- Smallest safe correction: state either that aspect_results is mechanically derived by Laravel from answer_parts'/unsupported_aspects' already-validated content (not independently model-generated), or specify the exact deterministic check Laravel runs to reject an inconsistent pair before persistence.
- Why before R01P: coverage reporting is central to R01P's own success criterion ("visible missing-evidence limitations are demonstrated") — this is the mechanism the whole slice is being built to prove out.

Non-blocking observations

- The R00 baseline is unusually well-grounded: I spot-checked EligibilityResolver::distinctComparisonVersions, resolveLocationReference, and planner.py's _normalise_temporal_plan — all exist and match the described reproduction shape. Not invented.
- The proposed Stage 28.4 guide clarification (R00.3) is additive, doesn't weaken S05/S06/S06's obligations, and agrees with the guide's actual current text (I re-read Stage 28.4–28.6 directly; nothing has drifted from what's described).
- §5's worked examples otherwise agree with the schema and prose (shape-vs-semantics separation is handled honestly — the document repeatedly and correctly says the schema can't judge language, only structure).
- §6's build-vs-adopt reasoning is grounded in Dolved-specific gaps (authority, applicability, evidence pairing), not framework preference — no issue there.
- Effort/go-hold criteria (§7–§8) are concrete and appropriately humble about sample size.

Closing

This review does not claim the proposal already works, that R01P is complete, or that Dolved is pilot-ready. It also doesn't claim R00 or the diagnostic work behind it is untrustworthy — the opposite: it's the specificity of that work that made these three gaps findable. Once the three blockers above are resolved (each is a paragraph, not a redesign), this is close to a reasonable R01P starting point.
