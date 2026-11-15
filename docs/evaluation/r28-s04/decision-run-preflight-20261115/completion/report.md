# Phase A completion findings — 15 November 2026

Starting/published preflight commit: `a5ffd13e5ace0554c8d9073375d1928fd4db2cf5`.
Hold branch verified; application runtime remains
`b73d054d9492e909cfaeadb642544950cfe940de10bfcbe12b388b089997b901`.
No application changes, full-suite rerun, provider calls, live authority records,
dispatch, reviewer expectations access, main merge or closure. Prior evidence is
unchanged. This completes the requested findings, not permission to execute Phase B.

## 1. Two distinct product defects

Source references below are repository-relative, at the frozen application commit.

| Layer | Exact path and result | Evidence level |
|---|---|---|
| Backend result rendering | `GenerationResult.php:53–95` adds scope facts, temporal basis/versions/intervals, incomplete targets and qualified unsupported-aspect disclosures to raw parts | Code-traced; temporal basis directly present in all five retained capture fixtures |
| Final backend response and saved message | `OrchestrateConversationRun.php:404–408` appends unresolved named-location coverage **after** rendering, saves that string; `:433–448` sends full text plus separate raw parts/citations | Saved text observed in the five retained real-service development fixtures; SSE construction code-traced |
| Persisted answer parts/context | `PersistGeneratedAnswer.php:73–77,128–140` saves unsupported aspects, scope facts, temporal context and each **raw** part with its citations; it does not save the orchestrator's appended location notice as part text | Code-traced |
| Reloaded backend answer | `GeneratedAnswer.php:53–63` reconstructs GenerationResult with scope/temporal/aspect fields, but not the evidence-needs metadata or the outside-renderer location append | Observed: two retained fixture re-renders lose exactly the location notice; temporal basis survives |
| Reloaded conversation API | `ConversationResource.php:25–33` returns saved message text, while `:49–61` separately returns raw parts, scope citations and unsupported aspects | Code-traced; not a fresh HTTP/browser replay |
| Web live completion | `ChatWorkspace.tsx:162–175,291–300` stores event parts, then refreshes conversation; `:481–507` prefers those parts and renders `part.text` instead of saved `message.text` when parts exist | Code-traced, not browser-observed |
| Web after reload | Same render branch selects `run.answer.parts` when no live completed-parts override exists | Code-traced, not browser-observed |

Paths: PHP model/support paths under `apps/api/app/Models/GeneratedAnswer.php`,
`apps/api/app/Support/Generation/GenerationResult.php`; actions under
`apps/api/app/Actions/{Conversation,Generation}/`; resource under
`apps/api/app/Http/Resources/`; web under `apps/web/src/components/`.

### Exactly which disclosures are affected

For an answer with non-empty raw parts, the web code does not present disclosures
that exist **only** in the rendered/saved text:

- **Location coverage not established**: append helper
  `UnresolvedNamedCoverageDisclosure.php:11–32`. Lost both in the independent
  re-render path and in the part-based web path, for different reasons.
- **Temporal basis**, selected version(s), effective interval, end-exclusive
  boundaries and disagreement with the provisional planner target:
  `GenerationResult.php:61–70`. Survive backend re-render; absent from raw parts
  and hence code-traced absent in the parts-based web view.
- **Recorded policy scope** and the deterministic scope facts, including the
  “Other requested guidance” separation and scope-only-does-not-answer-operational
  caveat: `:55,73–79`. Scope facts persist and can re-render, but the web does not
  consume `scope_citations`. If there are no parts, saved message fallback can show
  the scope text; do not generalise the omission to every scope-only response.
- **Temporal evidence incomplete**, including capacity-limited/unsearched targets
  and the distinction from missing source information: `:80–84`. Context persists
  but this additional text is not in raw parts. This finding is code-traced, not
  demonstrated by the five fixtures (their retained marker checks are explicit).
- **Unsupported requested aspects are not generally hidden**: the web separately
  renders `unsupported_aspects` at `ChatWorkspace.tsx:507`, including budget-cut
  wording already embedded there. `insufficiency_reason` has its own Notice at
  `:506`. These are not classified as lost merely because message.text is ignored.

The same part-versus-rendered split also bypasses renderer-only suppression of an
unsupported binary lead (`GenerationResult.php:58–60,98–111`). This is a code-traced
delivery risk, not a new live observation or a newly graded question.

### What the failed assertions prove

`rendering-fixtures.json` derives only from the existing five capture records.
`modified_input16` and `c2_original_input16` have a saved assistant message containing
the location notice; their freshly reloaded `GeneratedAnswer::renderedText()` lacks
it. Both raw part lists lack the temporal-basis and location text. Three other
fixtures have equal backend representations, with temporal basis still absent
from raw parts. The failed equality assertions therefore prove a **backend
representation discrepancy**; they do not execute React and cannot prove a browser
observation. The independent frontend code path explains an additional delivery
defect, not the backend failure. No failed assertion was changed or rerun here.

**Disposition:** both are confirmed product defects. An explicitly backend-scoped
decision run may proceed if David approves its scope; these defects block pilot
readiness wherever required disclosures are hidden. Backend correctness and
user-visible delivery require separate findings. A backend answer that depends on
a hidden required qualifier must carry a delivery-failure flag even if backend
correctness passes. Saved text is not proof of what the web user saw.

Eventual requirement, not a repair design: each required disclosure appears **once**,
with citations preserved, consistently live and after reload. Do not duplicate
the full saved message alongside the same parts. Presentation verification should
reuse retained responses rather than buy another generation run for rendering.

## 2. Corpus reconciliation

`corpus.json`, `manifest-reconciliation.json` and `index-counts.json` are new
read-only observations on the isolated stack. Original document public-ID sets
match the S03 materialisation manifests exactly in all three scopes.

| Scope | Original documents/chunks | Current documents/chunks | Change |
|---|---:|---:|---|
| Primary | 300 / 982 | 304 / 986 | Four added one-chunk bait documents; zero removed |
| Foreign tenant | 12 / 12 | 12 / 12 | No added/removed chunk identities |
| Dedicated injection pack | 6 / 6 | 6 / 6 | No added/removed chunk identities |

The four primary additions are document IDs319–322, chunks1001–1004:
`v4.injection.compliance-override-bait.md`, `citation-suppression-bait.md`,
`impersonation-approval-bait.md`, `workspace-enumeration-bait.md` (all with the
`v4.injection.` prefix). Their recorded creation/effective dates are17 September2026;
exact UUIDs and dates are retained in corpus.json. They are **not** the six original
dedicated-workspace injection documents, nor foreign-tenant records. This reconciles
the original1000 active chunks (982+12+6) with current1004 (986+12+6), not a claim
that all1004 are eligible to the primary workspace. Superseded index generations
are also not additive active evidence. The original primary manifest did not
include these four later additions; no retrospective manifest rewrite is implied.

Current primary generation `b24a79c2-875e-4f7c-8fa9-3681db92ae73`, foreign generation
`044a9260-b265-4179-8e34-3c0708b55b67`, dedicated injection generation
`461b4f51-c1d7-4a61-aceb-3ddc08581a3c`, all in `rag-platform-vectors-v1`, each has
its own workspace-filtered point count equal to database assignments, and zero
foreign assignments/points within that generation. Prior S03 generations/collection
and digests remain recorded, not substituted for the current ones.

K1 is labelled tenant-isolation; K2 injection-resistance. The release does not
specify execution workspace/context. The inherited dispatcher is primary-only.
**Do not silently dispatch K2 in either primary or the dedicated pack**: confirm
the intended context map. If the dedicated injection workspace is intended, the
existing serving sidecar intentionally refuses that workspace; capture availability
must be disclosed rather than widened. Original v13/v13b evidence already records
the consequence of testing an injection question in the wrong workspace.

Reviewer-reported evidence: source, ingestion and timeline checks completed on the
running stack with zero differences. This is recorded as reported, **not claimed
independently repeated**. Our narrower independent checks are assignment/manifest
identity reconciliation and scoped index counts, not all source/timeline checks.

## 3. Three different budget quantities

1. **Credible expected-range estimate: unavailable for the current package.** The
   coverage selector has no live measurement yet; expanded review receipts and
   retry/failure mix are not measured on this package. Older run subtotals omit
   priced stages and use earlier contracts. They cannot honestly supply an
   end-to-end expected range. The guard below is not an expectation.
2. **Conservative reservation: USD324.64135 (round up324.65)**, unchanged from the
   published stage-by-stage calculation. Subtotal259.71308 plus64.92827 uncertainty
   allowance. Retained, not freshly verified, pricing; local/unmetered costs remain
   explicit and provisionally covered, not zero.
3. **Proposed enforced spending stop: USD649.28270 (round up649.29)** for David's
   approval, using `max(50,2×reservation)`. No spending authority is created here.
   Enforcement must reserve outstanding maximum attempt liability before each
   dispatch, not wait until a known-cost subtotal crosses the stop; missing costs
   remain charged to conservative reservations.

The largest allowance is reranking141.97584 across34, followed by LLM stages.
Existing bounds can tighten estimates without application changes:

- Planner request bytes are explicitly limited to100000 before transport
  (`needs_planner.py:835–842`); reserving400000 input tokens per call is very loose.
  A replacement should include provider framing, not equate prompt text alone with
  the complete request. Family review has12 candidates,2000-character excerpts,
  255-character titles and8000-character question (`compare_family.py:102–117`),
  also materially below whole-model-context sizing after encoding/schema allowance.
- In this released, single-turn population, maximum question length is218 UTF-8
  bytes. `EvidenceNeedsPlan::compiledRequest():57–70` concatenates at most nine
  grounded spans (four context, question, authority, fact, applicability, claim),
  so a single-turn bound is1970 bytes including eight separators, not32000.
  This can greatly tighten embedding/reranking allowances **after** the final
  context map confirms no added user turns. It is not a relevance or answer claim.
- Generation/source review retain400000-token context allowances. Five final
  physical passages, the measured primary maximum2798 chunk bytes and bounded
  assembled fields can support tighter complete-request sizing; do not simply
  substitute passage tokens for all schemas, associations, claims and context.
- Selector already reserves its enforced30000 input /4096 output, one call, no
  retry. Generation output4096 and source-review output17408 remain unchanged.
  Source review and entailment are one call, not two named charges.
- Four ordinary lanes plus three discovery plus three alternatives are deliberately
  overlapping upper bounds: resolved C2 may reuse discovery candidates; unresolved
  discovery does not run alternatives; capacity can suppress extra searches.
  Maximum retry counts and maximum successful downstream work also need not
  co-occur. Retain conservative charging for paid attempts whose response is lost.
- Published multiplicative bounds remain: up to2 outer planner transports ×2 plan
  attempts, one checker per accepted proposed plan; at most3 needs ×2 family
  transports; 2 generation validation attempts ×3 Python transport attempts, each
  potentially including sequential generation and review, no SDK retry. Existing
  completed validation exhaustion is not permission for another outer retry.

No ceiling, reservation, timeout, token cap, retry or provider setting is lowered
in this task. These are identified tightening opportunities, not an unapproved
replacement budget. Provider-free sizing is not a promise of completion within
the existing provider/outer HTTP timeouts.

## 4. Capture activation and harness

See `activation.md` for exact bounded steps and the missing-override prerequisite.
Serving capture remains off; no activation or authorisation occurred here.
Existing capture retains accepted plans, rejection stage/field/code, C1/C2 routes,
discovery/selection lineage, temporal targets/uses, candidate allocation/proposals,
unmet coverage and independently validated receiving associations, generation
requests/evidence identities, answers/citations, saved and re-rendered text,
per-stage usage/latency and typed failures. Existing sidecar adds allowlisted
rejected proposals and actual normalisation counts when enabled for the authorised
workspace. Raw internal source-review JSON remains unavailable; categorical
diagnostics do not reconstruct it. Previous provider-free capture/control evidence
is reused, not renamed as a new serving-route observation.

The original release is hash-verified at
`decision-run-20261115/released-input/questions-only.json`:
`4c6ad785d33fe032b57541fb6fcf9b06f81a48391bd4bf9e7b43bcc852769048`.
Inventory: O1–O12, H1–H4, C1–C4, M1–M4, K1–K4, R19/R24/R26/R28/R29/G3,
in that file order. 24 fresh,4 controls,6 regressions. The adapter preserves exact
wording, emits only id/utterance to dispatch and keeps grouping in the ledger.
It binds both original-file and derived execution hashes to future authority;
requires confirmed context; delegates once-only controls to the original frozen
checkpoint. Six provider-free adapter tests pass. No released question was sent
to an adapter/provider, graded, substituted or used as a test expectation.

## 5. Decisions and mechanical steps before Phase B

David must approve: (1) explicitly backend-scoped decision-run interpretation,
with separate delivery failures and the rendering defect still blocking pilot
readiness; (2) the proposed spending stop; (3) the reviewer execution-context map,
particularly K1/K2. No new design comparison is requested.

Mechanical prerequisites: recover/verify the missing runtime provider override;
bind the released scope map and exact input/harness hashes; bind the new evidence
destination; enable and verify workspace-scoped capture provider-free; verify
unchanged runtime, service identities and accounting bindings. Only then may a
separately approved Phase B create/consume a fresh one-use authority and execute.
No implicit dependency starts, migrations, role changes or corpus writes.

Necessary isolated services remain running; workers, publisher and role-bootstrap
remain stopped. Journey locally only. Block1 HOLD; main unchanged.
