# Explicit location descriptors — bounded R01P correction

2026-09-07. R28-S04 / R01P remain in progress. No commit or push; await review of this coherent uncommitted correction. No R02 or new full confirmation set.

## Confirmed diagnosis before implementation

Entry HEAD was `f214e0f9118b21fb941150e4a3c697bc0ba1f199`; runtime aggregate matched `2881d2078fcced5f8fb0757f144d07a92040a2da2ec4de7528863a0b45f37f40`. Only the disclosed plan/tracker tracked metadata changes were present. The previous confirmation report remained an unrelated-to-this-edit untracked handoff.

The immutable `/tmp/dolved-confirmation30-v4.Zlwq1c/run/f01.json` records planner `location_references: ["Riverside"]`, then Laravel eligibility `unresolved_location_reference`, no resolved location and no retrieval/generation. The actual isolated registry contained `Riverside House`, aliases `manchester home` and `the home`, but no `Riverside` alias. This was exact-match failure in Laravel, not incorrect planner extraction. The registry had no descriptor designation; David explicitly approved introducing per-location data rather than guessing from a global word list, and approved seven fictional fixture mappings.

## Implementation and boundaries

- Add nullable `OrganisationalLocation.reference_descriptor`; existing locations default to no designation. Model validation requires a trailing whole phrase after a nonempty specific name. Renaming cannot silently leave an invalid designation. Matching revalidates the suffix even if a raw database write bypassed the model.
- Preserve exact canonical/alias matching and existing controlled legacy normalization precedence. Only when those fail, compare the reference with each authorised workspace location's explicitly designated shortened name. One match resolves; multiple matches return the existing ambiguous-location outcome. No recursive stripping, no inferred descriptors, no site-name branches.
- Suggestions are separate: registered canonical names/aliases/designated stems matching exactly, a whole-word prefix (minimum four characters), or one character edit (minimum four reference characters). Suggestions never auto-resolve; unmatched references retain the generic question. Candidate names are workspace-scoped and sorted. This adds no semantic/model judgement or retrieval-quality threshold change.
- Carry the server-built clarification through eligibility, needs retrieval (including revalidation), and the controlled renderer. No needs schema, provider/model, catalogue binding, evidence threshold or index changes.
- Fixture mappings live in `apps/api/tests/Fixtures/alderbridge-location-descriptors.json`, not application matching logic. An isolated, scope-checked, transactional model update applied them to the actual evaluated registry. Before/after evidence and fixture SHA are retained. The normal migration-owner service applied the additive migration to `dolved_e2e_r28_s03`; the initial application-role attempt was correctly denied before any DDL or fixture update. No privilege broadening was made.

| Actual registered location | Approved descriptor | Verified shortened name |
| --- | --- | --- |
| Riverside House | House | Riverside |
| Oakfield Lodge | Lodge | Oakfield |
| Moorland View | View | Moorland |
| Harbour View | View | Harbour |
| Meadow Court | Court | Meadow |
| Central Support Office | Office | Central Support |
| Willow Bank Community Service | Community Service | Willow Bank |

All seven resolve uniquely. The three regional records remain undesignated. Workspace `5323cd6b-8b7a-40f9-98b0-9413c75f643e`, active generation `b24a79c2-875e-4f7c-8fa9-3681db92ae73` and 982 chunks are unchanged. This is explicit fixture configuration, not automatic production-registry population or a new management UI.

## Provider-free proof

| Synthetic scenario | Result |
| --- | --- |
| Fern House, designated House; reference Fern | Resolves Fern House |
| Fern House + Fern Lodge, both designated; reference Fern | Ambiguous; names both candidates; does not search |
| Fern House; reference Zebra | Unresolved; original generic clarification |
| Fern Bank, undesignated; reference Fern | Unresolved; suggests actual Fern Bank only |
| Fern Bank undesignated + Fern Lodge designated | Resolves designated Fern Lodge; does not invent a Fern Bank shortening or false collision |
| Willow Bank Community Service, designated Community Service | Willow Bank resolves |
| Same location, reference Willow | No recursive shortening; unresolved with named suggestion |
| Exact canonical Fern + designated Fern House | Exact canonical Fern retains precedence |
| Ferm typo near designated Fern House | Suggestion only; unresolved |
| House of Fern, undesignated | Internal word is not stripped; prefix supports clarification only |
| Foreign-workspace Fern Court | Never resolves or appears in candidate clarification |
| Whole name used as descriptor; rename leaving stale descriptor | Model rejects both |
| Ambiguous Fern through actual handleNeeds and renderer | Both candidate names survive; zero HTTP calls |

- 266 Python tests passed: evidence needs/provider schemas, generation needs output/transport/request, aspect coverage, generation contract/OpenAI adapter, safe diagnostics, validation retries and 26 polarity fixtures.
- 218 Laravel tests / 893 assertions passed: semantic and hybrid retrieval, needs client/retrieval, generation foundation, conversation orchestration and needs/evidence/aspect unit suites. Three opt-in cross-service cases skipped here and then separately executed: **3 passed / 37 assertions** using fake external providers.
- Focused new tests: 4 methods, including the ten-scenario matrix and cross-boundary propagation. The first three passed 35 assertions; the cross-boundary method adds four assertions.
- Explicit nine-file Pint formatting/check passed. Scoped Ruff lint/format and mypy passed for the unchanged planner/generation boundary (two files). PHP has no configured static type-analysis dependency; typed execution tests, syntax checks and Pint cover the changed PHP boundary. No Python source changed.
- JSON parsing and git diff --check passed. No provider-free check called a provider. Initial test command used a nonexistent test filename and exited without running; the corrected complete command above passed.

## Frozen five-case live regression

HEAD remains the same local commit, with this correction explicitly **uncommitted**. The 941-file runtime aggregate was frozen before dispatch and unchanged afterward:

`1b036becd5fb22bfcd92ce6a988f649a8b33dec2c922fc35a2564becb539b7e1`

Question file SHA: `6d99095944b2a788de1523bba33195bb369d2af74bf498fe4d12b00518aa6e46`. f01/f09/f18 were copied verbatim from the approved fourth-confirmation input. The two new ordinary-current questions were fixed before dispatch: “What should staff wear while on duty at Moorland?” and “What should staff wear while on duty at Oakfield?” No answer key exists for those checks; outcomes are reported, not graded.

| Case | Actual location | Retrieval | Generation |
| --- | --- | --- | --- |
| f01 | Riverside House (`c2db9bba-c9ee-4736-b8bb-1ade44e1a766`) | evidence_found | qualified |
| Moorland | Moorland View (`b14306d8-ed5b-42c3-85c0-1cf4cb02b96b`) | evidence_found | answered |
| Oakfield | Oakfield Lodge (`e274a890-0b0c-4188-9ee6-66d068bb30e8`) | evidence_found | answered |
| f09 control | No location narrowing | evidence_found | answered |
| f18 control | No location narrowing, two needs | evidence_found | answered |

The exact f01 location failure is resolved. Its answer gives general uniform requirements and advance manager approval for alternatives, while recording a gap about whether Riverside specifically permits the fleece substitution. This qualification is retained honestly, not treated as an unconditional yes/no or automatically declared correct. Moorland/Oakfield receive dress-code guidance. Controls report 31-day CCTV retention and, for f18, personnel-file retention of six years after employment ends; no control execution regression observed. This is a known-case regression, not independent correctness scoring.

All five ran once, all completed, no native or validation retry. **22 provider requests, 215,838 input / 5,988 output tokens, 113.151503 seconds, estimated USD 0.05750555** against USD 5 / 90 minutes. No missing usage rows. Retry-driven cost/time zero. No re-embedding, changed indexes, answer-key access, selective rerun, AWS, commit or push. All citations resolve in the primary workspace. The 33 unrelated untracked files and prior evidence remain unchanged. Task-generated type-check cache was removed; temporary test/live processes were stopped.

## Evidence and files

Evidence root: `/tmp/dolved-location-correction.sUFfhy`.

- [Full five answers, citations and receipts](/tmp/dolved-location-correction.sUFfhy/actual-results.md).
- [Machine-readable observations](/tmp/dolved-location-correction.sUFfhy/observations-and-citations.json).
- [Registry before/after](/tmp/dolved-location-correction.sUFfhy/registry.json).
- [Source freeze](/tmp/dolved-location-correction.sUFfhy/code-freeze.json).
- [Exact changed-file hashes](/tmp/dolved-location-correction.sUFfhy/changed-file-hashes.json).
- [Evidence checksum inventory](/tmp/dolved-location-correction.sUFfhy/checksums.sha256).

Implementation/test/fixture files: `OrganisationalLocation.php`, `LocationReferenceCandidates.php`, `EligibilityResolver.php`, `EligibleRetrievalScope.php`, `RetrieveWorkspaceEvidence.php`, `ControlledResponseRenderer.php`, the additive migration, `SemanticRetrievalTest.php`, `EvidenceNeedsRetrievalTest.php`, and `alderbridge-location-descriptors.json`. This report and the plan/tracker carry the handoff; exact paths/hashes are in the inventory. No unrelated implementation change or new ADR.

Commit boundary: retain one coherent uncommitted correction for review. R28-S04/R01P remain in progress; stop here, no R02 or next question-set authoring.
