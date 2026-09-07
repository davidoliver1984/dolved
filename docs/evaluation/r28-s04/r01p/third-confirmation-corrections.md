# Third-confirmation corrections and eight known-case regressions

2026-09-06. R28-S04 / R01P remain **in progress**. No R02, commit, push, selective rerun or independent confirmation is implied.

## Identity and authority

Initial HEAD `6fdc2981732773ff08be8e3b5c1f505112501c87`, clean tracked tree and runtime aggregate `b4dcff026fc21e72e06cfe36cf6a3f69d527513853ae1d63b1a411d2a0a4bac7` were verified before implementation. David authorised the bounded changes and these eight known cases once under USD 5 / 90 minutes, then explicitly approved treating e14 as upstream planner judgment rather than relaxing Laravel enforcement. These corrections remain **uncommitted pending David's decision**.

Corrected runtime aggregate: `c2ba8af7c305e9ca69b245d6d2b595ef3b23728f31fc656fa56f180584577de2` over 935 source files, frozen before dispatch, verified against both mounted services and unchanged afterward. Planner prompt `bounded-evidence-needs-v10`; execution pin `bounded-needs-slice-v9`. Provider/model, thresholds, generation prompt/adapter, retry settings and index unchanged. Primary workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e`, active generation `b24a79c2-875e-4f7c-8fa9-3681db92ae73`, 982 chunks reused.

Source questions-only file SHA-256 `4d789472dd2e20feab3669a278643dd74f55ab29a8ec64632ba9374835157dce`; exact eight-question extraction SHA-256 `4f2074889669726613cb9ed9e44fa9fe28ddf102f7f96083de8de8fa004e6cae`. No answer key accessed or requested.

## Diagnosis and implementation

1. **e08/e16/e28:** prior records showed five selected evidence items and one failed generation attempt, but Laravel discarded the transported category/status. An external outage was not established. `GenerationProviderException::safeDiagnostics()` now retains an allowlisted category, valid HTTP status or null, attempt count and latency; orchestration preserves existing usage when recording them. The existing provider error contract supplies no validation field, so `field` is explicitly null, not reconstructed. This is diagnostic coverage, not a claimed root-cause fix.
2. **e14:** the planner bound exclusively to Equipment Inspection Schedule, so Laravel correctly excluded Workplace Risk Assessment Form before search. The latter's worked-example table contains the quarterly cable-routing action. Prompt v10 instructs ordinary topical questions to remain `topic` with empty family IDs unless the user actually names/references a document. Explicitly requested documents remain narrow, including unavailable named subjects. No Laravel eligibility/catalogue binding rule changed and no search/reranking tuning was added.
3. **e03/e26:** prior plans invented three policy-based needs from one requested task. Prompt v10 distinguishes user tasks from possible supporting documents, while retaining separate needs for genuinely independent questions. This is model judgment, not a deterministic semantic override. Prompt assertions and scope tests cannot alone prove that judgment generalises.

## Provider-free verification

- Python: **217 passed** across evidence-needs, provider schema, generation needs request/transport/output/aspect coverage, final generation contract and OpenAI adapter suites.
- Laravel: **210 passed / 849 assertions**, with three bridge tests initially skipped because their endpoint was not configured; those same three tests subsequently **passed / 37 assertions** against a fresh provider-free Python recording bridge.
- Scope test proves broad topical current eligibility and exclusive matched-family eligibility; does not claim the live planner will always choose correctly.
- Failure tests prove allowlisted contract-validation and rate-limit categories/statuses persist, unknown categories/invalid statuses are sanitised, raw/private strings are absent and no answer is persisted on failure.
- Ruff lint/format passed; Pint passed on all seven changed PHP files; scoped mypy passed; `git diff --check` passed.

## Exactly-once live results

All eight reached retrieval `evidence_found`. Four produced answers; four failed generation. **This is not a clean overall pass, an independent confirmation or a general correctness score.**

| Case | Needs | Actual result |
| --- | --- | --- |
| e08 | 1 | Generation failed: `contract_validation_failure`, one attempt, HTTP status/field unavailable. |
| e16 | 1 | Same typed generation failure, one attempt. |
| e28 | 1 | Answered with daily pool-car walkaround, logbook and defect-reporting instructions; document 74, chunks 312/313. |
| e14 | 1 | Answered: “Cable routing for mobility equipment chargers should be reviewed quarterly.” Cites Workplace Risk Assessment Form, document 18 / public ID `81b225ad-8e3d-4e1a-b471-814bdef2d21f`, chunk 92. Its canonical text contains that quarterly action. |
| e03 | 1 | Answered with racist-visitor response/reporting guidance. Over-decomposition did not recur; substantive answer correctness is not independently graded here. Documents 7/40, chunks 36/37/44/182. |
| e26 | 1 | Did not exceed the need cap; retrieval succeeded, then generation failed with `contract_validation_failure`, one attempt. |
| e22 | 2 | Answered both visiting arrangements and fire-drill frequency. Documents 45/49/73/298, chunks 204/220/310/311/971. |
| e23 | 2 | Preserved uniform and hoist questions as separate needs; retrieval succeeded, then generation failed with `contract_validation_failure`, one attempt. |

The planner regression checks succeeded on these known inputs, including both two-topic controls. e23 nevertheless regressed at the separate generation boundary. All issued citations resolve within the primary workspace. Failed cases have no answer/citations; vacuous ID resolution is not answer correctness evidence. A fresh explicitly named-policy question remains reserved for David/Claude's next independent set; no extra live case was added.

All four observed generation failures expose the category but not the exact violated field/rule. They do not prove an external provider outage, and this task does not claim to have repaired them. No code was changed mid-run and no failed question was repeated.

## Usage and evidence

**36 provider attempts**, zero recorded retries: 8 planner, 10 query embedding, 10 reranking, 8 generation (4 successful usage receipts and 4 failed-attempt diagnostics). Known token totals **279,479 input / 7,450 output**; failed generation token receipts are unavailable. Configured-rate **partial estimate USD 0.06846118**, not an invoice or complete actual cost. Conservative budget charge **USD 4.04584282** includes USD 1 retained per unmetered failed case, within USD 5. Elapsed **173.241 seconds**, within 90 minutes. No judge, re-embedding, AWS or additional run.

Evidence package: `/tmp/dolved-r01p-round4.Fz1xRX/`. `run/` holds original case, dispatch, usage, manifest and completion records. `observations-and-citations.json` holds full production traces, rendered answers and resolvable cited canonical chunks; `actual-results.md` contains all eight questions/full answers/safe failures; `summary.json`, `code-freeze.json`, `questions.json` and checksum inventories support verification. Original third-confirmation evidence remains separate and unchanged.

Next action: David reviews this mixed result and decides the commit boundary. The unresolved generation-contract failures remain explicit. No further correction, paid run, new questions or R02 is started automatically.
