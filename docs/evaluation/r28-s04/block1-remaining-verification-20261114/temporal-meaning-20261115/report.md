# Temporal meaning correction — 15 November 2026

Provider-free implementation. Hold branch from `72a83ab7d0565e73aa83a18fa47a7afb6b293038`.
No provider calls, deployment, reviewer/ sealed access or main change.

## Confirmed mechanism and bounded correction

Item 19's retained `../run/cases/19.json` records compare/historical_reference,
selector `February 2026 update`, and `ambiguous_authority_window_for_period`.
The original resolver reduced that selector to 2026. Current precision-preserving
resolution selects the February version in a controlled timeline using the retained
source's effective dates; current versus that same version is incomplete, not a
transition. The corrected transition_period representation resolves its authorised
predecessor/successor. This is a provider-free replay, **not a new runtime outcome**.

G3's captured proposals in the temporal-association diagnostic both used
after/day/2025-07-01. Their spans say the schedule started on that day and ask what
applied once it started; neither contains the grounded after relationship required
by the unchanged validator. Further, after/day means the next day, not the activation
day. Date authority preserves the requested state on activation. This does not
assert a different version necessarily existed the next day in the real corpus.

Existing guidance was terse and distributed: the explicit transition instruction
coexisted with “only when two versions are actually requested” and named-selector
guidance, without explaining that a dated update requests its transition pair.
Prompt v27 reconciles those instructions in place using unfamiliar illustrations.
No phrase lists, prompt fitting to retained questions, code-side semantic rewriting,
validator relaxation, extra checker/planner call or attempt change.

Existing compare validation stays distinct-version throughout eligibility, ranked
candidate selection and generation. Two dated states can use separate retrieve/date
needs and resolve to one version; this does not establish unchanged operational facts.
Missing transitions retain an authority-specific reason through aggregation/rendering;
ambiguous transitions ask which update, not for a date already supplied.

## Boundaries and coordinated identities

- Planner guidance: `apps/ai/app/retrieval/needs_planner.py` temporal section,
  change-versus-snapshot instruction, named history/previous guidance and the later
  precedence paragraph reconciled together. The literal before/after grounding
  prerequisite is explicit, not relaxed or treated as a semantic keyword mapping.
- Actual producer **bounded-evidence-needs-v27 / structured-chat-needs-v12**,
  plan schema **v4**, transport unchanged. PHP's execution consumer pin and current
  fixtures advance together. The persisted `GenerationProfileFactory::needsIdentity`
  planner fields were stale v3/v25/v9: they now match the actual v4/v27/v12 producer,
  asserted against the Python-produced artifact, while execution advances to **v24**.
- Only transition outcome reasons/rendering change in execution. Timeline,
  applicability, permissions, distinct-version comparison checks, ranking, thresholds,
  validators, limits and retries are unchanged. Controlled renderer **v2**.
  Existing date states resolve through retrieve/date lanes; the `compare` operation
  remains a document-change request. No new two-date wire format and no blanket
  rejection of two dates resolving to one version.
- Generation remains v6 / needs prompt v15 / adapter v7; review remains v2 with
  17,408 tokens. Selector and receipt design untouched. Historical records are not
  rewritten; new execution cannot silently reuse an obsolete deployment pin.

## Provider-free verification and limitations

| Check | Evidence/result |
|---|---|
| Focused AI contracts, adapter, grounding and schema | `ai-focused-pass.xml`: 184 passed |
| Focused PHP producer consumption, real timelines and retained item19 replay | `api-focused.xml`: 41 passed /98 assertions |
| Full final AI | `ai-final.xml`: 1,538 tests; 1,496 passed, 38 failed, four skipped |
| Full final PHP | `api-release.xml`: 953 tests; two failures, nine skipped |
| Exact retained failure identities | `suite-comparison.json`: zero new, zero absent/resolved in both services |
| Static checks | Scoped Ruff/format and Pint passed; Mypy 169 source files passed with an isolated cache |

The real Python adapter with controlled HTTP/interpretation responses produces
`contracts/http/retrieval-needs/v4/temporal-meaning-examples.json`. PHP consumes those
plans, substituting only synthetic authorised family IDs. Ten unfamiliar fixtures
cover month update, day transition, dated state, requested interval, genuine before/
after, current authority with background event, previous, missing transition and
multiple transitions. Further tests cover conflicting selectors, two dates resolving
to one version, and activation-day versus next-day authority. Missing-transition
reason is tested through real needs retrieval aggregation and controlled rendering,
with no search sent. Existing distinct-comparison, tenant and applicability negatives
remain unchanged and run in the full suite.

The item19 regression uses its retained plan in a controlled application timeline
based on the source-effective dates (2025-02-01 and 2026-02-01), not a fresh production
database resolution. Its old year-level ambiguity, precision-corrected same-version
incompleteness, and deliberate transition-period pair are separately asserted.
G3's retained captured need fields are wrapped in an explicitly synthetic envelope
for fields absent from the allowlist (diagnostic aspect/support edge and ownership
metadata). Its unchanged after proposal rejects twice at temporal_relation_grounding;
changing only authority.kind to date is accepted by the real adapter/validators.
Neither test reconstructs missing original output or replaces earlier verdicts.

Prompt-content assertions prove guidance exists; controlled plans prove downstream
handling. Neither measures live semantic selection or a failure rate. Need-count
capacity still applies to separate dated requests. Sharing a version does not supply
missing operational facts, and generation's source-support checks still apply.

### Retained setup/development failures

The early focused artifacts retain fixture-development failures: explicit regeneration
was first run from `/app` rather than the repository mount; a clarification fixture
lacked evaluated_at; and the G3 synthetic envelope initially lacked fields/completion
metadata required to exercise the existing two-attempt path. Final assertions were
not weakened. `ai-focused-final.xml` is an intermediate failed artifact despite its
name; only `ai-focused-pass.xml` and the full release JUnit are final verification.

The first AI full run had telemetry disabled by the isolated environment: 42 failures,
including five telemetry/metrics failures and an absent logging-dependent ingestion
baseline failure. With the retained baseline telemetry setting, the exact 38 recur.
The first PHP full process exhausted the default 128MiB CLI limit; its zero-byte
`api-complete.xml` is explicitly **not valid JUnit**. A test-only 1GiB process then
exposed two frontend-URL environment mismatches; using localhost:3000 restored the
retained two failures. These are not product fixes. Production timeout/memory/provider
settings were not changed. Mypy initially failed internally with its shared cache;
isolated non-incremental checking passed. The historically intermittent inode test
reproduced; no baseline failure is claimed fixed.

`ai-release.xml` additionally records one intermittent failure in
`test_r28_frozen_population_evaluation::test_canonical_frozen_candidate_accepts`:
its `git show` subprocess exited 128 while reading a pinned historical artifact.
The retained traceback does not expose the subprocess stderr, so the lower-level
reason is **unknown**, not asserted to be a specific filesystem error. The unchanged
isolated test passed (`ai-git-observation.xml`); a further full run is retained as
`ai-final.xml`. No test, git timeout or production code was changed to make it pass.

## Completion matrix and handoff

| Obligation | What changed/proved | Status and remaining evidence |
|---|---|---|
| Item 19 | Coherent update→transition guidance; controlled predecessor/successor resolution; same-version history remains insufficient for update; accurate unresolved/ambiguous transition responses | **Implemented/provider-free verified; awaiting live confirmation.** Model must actually select the transition and answer the requested change from both authorised versions in the decision run. |
| G3 | State-at-activation is explicitly date, distinct from day-excluding after and background agreement dates; unchanged rejection and accepted controlled date plan verified | **Implemented/provider-free verified; awaiting live confirmation.** Live role selection and source-based answer correctness remain unmeasured. |

Together with the separately published association, coverage-selection and bounded
source-review increments, this package is **ready to enter the single decision-run
preflight**, not a declaration that the decision run or Block1 criteria have passed.
No specific implementation blocker was found by this verification. Actual model
interpretation, selector usefulness and source-review fidelity/latency remain live
questions under the unchanged frozen criteria. Item17's separately documented genuine
family-review no_match is not explained or relabelled by this correction.

Preflight must bind the published runtime/producer and consumer pins, verify the
isolated deployment and frozen inputs, and recompute the full conservative reservation
before consuming authorisation. `prompt-sizing.json` measures the prompt-input delta;
no new model call/output allowance is added. The prior six-generation/six-review
transport envelope and selector bounds remain. Provider-free sizing is not a latency
guarantee. No new run, selective retry, readiness claim, main merge or Block1 closure.

`runtime-identity.json`, `runtime-source.sha256`, retained-source hashes, raw JUnit,
exact failure-set diff and `checksums.sha256` form the publication package. `verify.py`
reproduces the comparison and freeze. Repair-plan and Journey updates stay **local
only**, as requested; unrelated ADR and governed-runner/test edits remain untouched.
David's latest investment amendment withdraws charged/remaining-time accounting;
none is reinstated here. Frozen decision questions/criteria and earlier evidence
are unchanged. Stop after hold-branch publication.
