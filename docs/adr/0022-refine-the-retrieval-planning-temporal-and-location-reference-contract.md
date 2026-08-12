# ADR 0022: Refine the Retrieval Planning Temporal and Location Reference Contract

## Status

Accepted

## Date

2026-08-12

## Relationship to prior ADRs

### Extends ADR-0018; does not redefine its ownership boundary

ADR-0018 established that `RetrievalPlanner` interprets language and never resolves authority, document/version identifiers, aliases, applicability, permissions or retrieval scopes — that `EligibilityResolver`, Laravel-owned and deterministic, does all of that. A full engineering-benchmark run (`EXP-0001-alderbridge-initial-hybrid`) and four isolated, engineering-only planner experiments (`PLN-EXP-0001` through `PLN-EXP-0004`) were read against the current implementation before this ADR was drafted. That review found no evidence that ADR-0018's ownership boundary is wrong. It found that the **typed contract** carrying information across that boundary — `RetrievalPlan`'s `temporal_mode` enum, its `valid_at` field, and its singular `applicability_reference` field — was too narrow to let the boundary work reliably. This ADR is that contract's revision. It reaffirms, verbatim, ADR-0018's ownership decision:

> The planner interprets language. Laravel resolves application truth. The planner never resolves authority, document IDs, aliases, applicability, permissions or retrieval scopes.

Nothing in this document reopens `AuthorisedKnowledgeScope`, the three-way security/eligibility/descriptive metadata classification, the ADR-0016 dual gate, `EligibilityResolver`'s ownership, the `Retriever` contract, or the `rc1` protocol's synchronous call shape. All remain exactly as ADR-0018 defined them.

### Consumes ADR-0017 exactly as ADR-0018 already does

This ADR adds new deterministic resolution rules (a calendar-period resolver, a historical-reference resolver) that consume ADR-0017's `DocumentFamily`, linear lineage and attained-authority derivation, via the existing `DocumentAuthorityTimeline` service. It does not restate, approximate or reopen ADR-0017's domain model, including its predecessor-resurrection guarantee.

### Does not touch ADR-0019, ADR-0020 or ADR-0021

Evaluation metrics, quality-gate policy, model-assisted evaluation, hybrid retrieval, fusion, reranking and `EvidenceThresholdPolicy` are all out of scope. This ADR adds one new evaluation ground-truth obligation (see "Ground truth" below) but changes no metric definition or gate.

## Context

`EXP-0001-alderbridge-initial-hybrid` (2026-08-11) ran the complete 42-case / 126-variant engineering/tuning benchmark against the production pipeline. Two distinct problems showed up, and they must not be conflated:

- **39 of 126 variants failed to produce a valid typed `RetrievalPlan` at all** (failure category `invalid_typed_plan`) — a structural schema/parsing failure, not a semantic one.
- **Of the 87 variants that did produce a valid typed plan, 0 were temporally correct** (`Planner accuracy: 0.0000`). Clarification-heavy semantic behaviour — the planner reaching for `CLARIFICATION_REQUIRED` or an otherwise wrong mode rather than a truthful typed answer — occurred principally within this group of 87, not as an explanation for the 39 structural failures. The two problem classes are related (both stem from a contract too narrow to represent what the questions actually needed) but are not the same failure with two names.

Downstream retrieval metrics (Recall@K `0.0569`, Precision@K `0.0008`) were computed only over those 87 planner-successful variants and were also close to zero — a separate, unexplained problem this ADR does not address (see "Evaluation sequence after implementation" below).

Four isolated, engineering-only planner experiments, using only the 42 engineering/tuning cases and never touching calibration or held-out splits, then tested whether narrowing what the planner is typed to say — not what it's allowed to do — would fix this:

| Experiment | Change | Temporal accuracy |
|---|---|---|
| `PLN-EXP-0001` | Thin classifier, same three-mode contract | 92.86% |
| `PLN-EXP-0002` | Split applicability into plural location extraction | 90.27% (location precision/recall 0.80/0.80) |
| `PLN-EXP-0003` | Redefined COMPARE to exclude ordinary contrast | 100.00%, 0 false COMPARE |
| `PLN-EXP-0004` | Added `HISTORICAL_REFERENCE` as a fourth intent | 100.00% (126/126), diagonal 4-way confusion matrix |

`PLN-EXP-0004`'s confusion matrix was perfectly diagonal (CURRENT 88/88, COMPARE 22/22, VALID_AT_DATE 9/9, HISTORICAL_REFERENCE 7/7), with zero regressions from `PLN-EXP-0003` and zero false-date hallucinations. This is engineering-set evidence only — clean and internally consistent, but not yet validated against calibration or held-out data. It supports implementing the refined contract and testing it end to end; it does not by itself justify treating the numbers as production-representative.

## What this ADR decides and does not decide

**Decides:** the refined `RetrievalPlan` typed contract (four temporal modes plus a narrowed linguistic clarification escape, with a typed `temporal_reference` that preserves the calendar-period/historical-reference distinction the classifier makes); the semantic split between a calendar-period `VALID_AT_DATE` and a `HISTORICAL_REFERENCE`; a bounded deterministic calendar-period resolver; a shared deterministic historical-reference resolver reused by both `HISTORICAL_REFERENCE` and `COMPARE`; explicit COMPARE resolution rules; plural location-reference resolution rules including hierarchy and multi-location handling; the architectural invariant for location normalisation; enum-typed clarification reasons on both sides of the boundary; an additive versioned `RetrievalPlan` response schema under `rc1`; classifier lineage/fingerprinting; ground-truth versioning discipline for the four experiments' reconciliations; and migration approach.

**Does not decide:** ADR-0018's ownership boundary (reaffirmed, unchanged); ADR-0017's temporal-authority/lineage model (reused, unchanged); hybrid retrieval, fusion, reranking or threshold policy (ADR-0021, unchanged); the diagnosis or fix for `EXP-0001`'s near-zero retrieval recall (open, tracked separately); any UI, multi-tenancy, or unrelated roadmap item.

## Decision

### 1. `RetrievalPlan`'s wire contract

`temporal_mode` gains a fourth value; `valid_at` (a required `datetime` today, forcing the planner to invent a time-of-day for any `VALID_AT_DATE` answer) is replaced by an exact-date field and a **typed** reference field — typed specifically so that Laravel receives the calendar-period/historical-reference distinction the classifier already makes, rather than having to re-derive it from an undifferentiated string:

```json
{
  "temporal_mode": "current | compare | valid_at_date | historical_reference | clarification_required",
  "explicit_date": "YYYY-MM-DD | null",
  "temporal_reference": {
    "kind": "calendar_period | historical_reference",
    "value": "string"
  } | null,
  "location_references": ["string", "..."],
  "clarification_reason": "unclassifiable_temporal_intent | null"
}
```

Validation (`model_validator`, mirroring the existing pattern in `apps/ai/app/retrieval/models.py:64-86`):

- `temporal_mode == current`: `explicit_date` and `temporal_reference` both forbidden.
- `temporal_mode == valid_at_date`: exactly one of `explicit_date` or `temporal_reference` set; if `temporal_reference` is set, `kind` must be `calendar_period` — a `VALID_AT_DATE` question is inherently an authority-period question, never a document-naming one, and the schema enforces that rather than leaving it to be checked downstream.
- `temporal_mode == historical_reference`: `temporal_reference` required with `kind == historical_reference`; `explicit_date` forbidden — no case in the evidence needs an exact-date historical reference, and adding one later is additive, not a breaking change.
- `temporal_mode == compare`: `explicit_date`, `temporal_reference` (either `kind`), or neither — all valid (see "Calendar period vs. historical reference" below).
- `temporal_mode == clarification_required`: `clarification_reason` required and is `unclassifiable_temporal_intent` for V1 (its only member — see "Four temporal modes" below); `explicit_date`/`temporal_reference` forbidden. `location_references` remains independently valid in every mode, including `clarification_required` — location extraction is a separate linguistic axis from temporal classification, exactly as ADR-0018 already treats `applicability_reference` as independent of `temporal_mode`.
- The planner never emits a resolved date-plus-time, a document ID, a location ID, or an object naming `PRIMARY`/`COMPARISON` sides — those remain exclusively Laravel's to construct.

This does not expand what the classifier decides — it is exactly the same two-way distinction `PLN-EXP-0003`/`PLN-EXP-0004` already demonstrate a thin classifier makes reliably. The change is representational only: the distinction is now carried as structured data instead of being collapsed into one string and left for Laravel to reconstruct — and reconstructing it from free text a bounded deterministic grammar cannot fully disambiguate ("the 2023 one" is a calendar-period-shaped string that may mean either "what applied in 2023" or "the document called the 2023 one") would silently reintroduce the guessing this ADR exists to remove.

### 2. Four temporal modes plus a narrowed clarification escape

`CURRENT`, `COMPARE`, `VALID_AT_DATE`, `HISTORICAL_REFERENCE` are the production classification set, matching `PLN-EXP-0004`'s validated contract. `clarification_required` is retained but **narrowed** to genuine linguistic inability to pick one of the four modes at all — not the two reasons the current `ClarificationReason` enum carries today (`AMBIGUOUS_TEMPORAL_REFERENCE`, `MISSING_COMPARISON_ANCHOR`), both of which are now properly representable as typed values instead of forcing a clarification.

### 3. Calendar period vs. historical reference — the semantic line, and explicit COMPARE resolution

The distinction is decided by what kind of question is being asked, not by the shape of the date text:

- **"What applied in 2023?"** → `temporal_mode: valid_at_date`, `temporal_reference: {kind: "calendar_period", value: "2023"}`. An ADR-0017 authority-window question.
- **"What did the 2023 procedure say?"** → `temporal_mode: historical_reference`, `temporal_reference: {kind: "historical_reference", value: "2023 procedure"}`. A document-naming question that happens to use a year.

The classifier makes this distinction linguistically — the framing is an authority/temporal-truth question versus a document-naming question — which is exactly the open-ended natural-language judgment `PLN-EXP-0004` demonstrates a thin classifier can make reliably. The typed `kind` field preserves that judgment across the wire instead of discarding it.

Laravel routes deterministically on the typed fields, with **COMPARE's resolution fully explicit**:

| `explicit_date` / `temporal_reference` | Resolution |
|---|---|
| both null | previous attained-authority version (existing `previous`-anchor logic, unchanged) |
| `explicit_date` set | exact-date resolver (existing `DocumentAuthorityTimeline::resolve`, unchanged) |
| `temporal_reference.kind == calendar_period` | `TemporalPeriodResolver` (below) |
| `temporal_reference.kind == historical_reference` | `HistoricalReferenceResolver` (below) |

`PRIMARY` is always resolved at `CURRENT` for V1 — no evidence in hand supports comparing two non-current states.

For `VALID_AT_DATE` and `HISTORICAL_REFERENCE` outside `COMPARE`, the same two resolvers are used directly (`TemporalPeriodResolver` for `VALID_AT_DATE`'s calendar-period form, `HistoricalReferenceResolver` for `HISTORICAL_REFERENCE`), so no resolution logic is duplicated between the two call sites.

The LLM never manufactures a day. `explicit_date` is populated only when the question names an exact date; `temporal_reference` carries everything else, unresolved, tagged with the kind the classifier already determined.

### 4. Calendar-period resolution (Laravel, deterministic, no second LLM call) — `TemporalPeriodResolver`

A bounded parser — month+year, bare year, and quarter only if explicitly needed later — turns a `temporal_reference` of `kind: calendar_period` into a `[start, end)` window using ordinary deterministic date arithmetic, not free-form NL date parsing and not a second model call:

- If exactly one attained-authority version's window (per `DocumentAuthorityTimeline`) fully contains `[start, end)`, resolve to it.
- If the period straddles an authority transition, **do not** pick an arbitrary date inside it — return `ClarificationRequired` with reason `AMBIGUOUS_AUTHORITY_WINDOW_FOR_PERIOD`.
- If the text doesn't match the supported bounded grammar, return `ClarificationRequired` with reason `UNRESOLVABLE_TEMPORAL_PERIOD`.

### 5. Historical-reference resolution — one shared deterministic resolver — `HistoricalReferenceResolver`

Used identically by top-level `HISTORICAL_REFERENCE` and by `COMPARE` when its `temporal_reference.kind == historical_reference`. Rules for V1:

- **"version N" is an application-defined attained-authority ordinal, not a raw lineage-position or source/product-defined label.** No user-visible or source-defined version numbering exists anywhere in the current product — no `version_number`/`version_label` column, model attribute, or API Resource field, and no web-frontend display of a version ordinal (confirmed by repository search before finalising this ADR). The only ordinal in the system today, `version_position` (`RetrieveWorkspaceEvidence.php:386-393`, computed from `DocumentAuthorityTimeline::attainedVersions()`), already uses exactly the attained-authority definition this ADR formalises — so "version N" resolution reuses an existing internal concept rather than introducing a new one. A version scheduled and later cancelled before ever attaining authority does not occupy — and never occupied — a user-addressable ordinal.
- **"old"/"previous"** resolve, when unambiguous, to the immediately previous attained-authority version relative to `CURRENT` (one step back only; deeper relative references are out of scope for V1 and fail closed).
- **Year-qualified historical references** ("the 2023 procedure") route through the same period-window logic as `TemporalPeriodResolver`, applied to attained-authority windows, as the deterministic first strategy — reuse, not a second mechanism.
- **Ambiguous references** fail closed: `ClarificationRequired`, reason `AMBIGUOUS_HISTORICAL_REFERENCE`.
- **Out-of-range references** fail closed: `ClarificationRequired`, reason `HISTORICAL_REFERENCE_UNRESOLVED`.
- **A version that existed but never attained authority** must resolve identically, from the caller's point of view, to a version that never existed at all — the same fail-closed concealment discipline ADR-0006 already establishes elsewhere (404, not 403). The distinction may exist in logs/telemetry for operability, never in the response.

### 6. Location references — plural, deterministic, narrow-only

`location_references: string[]` replaces the singular `applicability_reference`. Resolution rules:

- Zero references → no location narrowing.
- One resolved reference → today's existing behaviour, unchanged.
- Ancestor + descendant in the same hierarchy → narrow to the most specific (descendant) location.
- Multiple resolved references with no ancestor/descendant relationship → `ClarificationRequired`, reason `MULTIPLE_UNRELATED_LOCATION_REFERENCES` — no reliable textual signal distinguishes "either site" from user confusion, and silently unioning would violate ADR-0018's narrow-or-unchanged invariant.
- Unresolved or ambiguous individual references → `ClarificationRequired`, reasons `UNRESOLVED_LOCATION_REFERENCE` / `AMBIGUOUS_LOCATION_REFERENCE`.
- Generic phrases ("the home," "our care home") remain unresolved unless some independently-decided application state can genuinely resolve them — none exists today, and none is introduced by this ADR.

### 7. Location normalisation — architectural requirement, not a specific mechanism

The requirement this ADR sets is the invariant: **location-reference resolution must be deterministic, collision-safe, and resolve only to registered `OrganisationalLocation` records** — a reference either matches a registered name/alias unambiguously or it doesn't resolve. Wording variants such as "the Bristol home" vs. "Bristol home" must be handled in a way that cannot cause a false match against an unrelated or differently-named location. Alias tables, normalised-alias columns, or controlled/reviewed article-handling rules are all acceptable *implementation strategies* for satisfying that invariant — this ADR does not mandate manually registering every grammatical variant of every location name, only that whatever mechanism is used cannot silently collide (for example, a blind unconditional strip of leading articles would risk exactly that, given a location whose canonical name legitimately begins with one — the invariant rules that out without prescribing the alternative).

### 8. Clarification ownership — enum-typed on both sides

Two clarification sources remain distinct and must not be merged, and neither uses free text:

- **Planner-side** (`temporal_mode: clarification_required`): a Python `ClarificationReason` enum narrowed to a single V1 member, `UNCLASSIFIABLE_TEMPORAL_INTENT` — genuine linguistic inability to determine intent at all.
- **Laravel-side**: its own separate, enum-typed application-state clarification reasons — `AMBIGUOUS_AUTHORITY_WINDOW_FOR_PERIOD`, `UNRESOLVABLE_TEMPORAL_PERIOD`, `AMBIGUOUS_HISTORICAL_REFERENCE`, `HISTORICAL_REFERENCE_UNRESOLVED`, `UNRESOLVED_LOCATION_REFERENCE`, `AMBIGUOUS_LOCATION_REFERENCE`, `MULTIPLE_UNRELATED_LOCATION_REFERENCES` — promoted from today's ad hoc strings in `EligibilityResolver.php:206,209` to a proper PHP enum, following the existing `RetrievalOutcome`/`RetrievalTemporalMode` pattern in `apps/api/app/Enums/`. Discovered only after linguistic interpretation already succeeded.

This ADR explicitly does **not** restore the original planner's broad `CLARIFICATION_REQUIRED` behaviour.

### 9. Response contract versioning

`plan-v1.schema.json` (the request) is unaffected and remains immutable as the historical record of what v1 meant. There is currently no formal response schema under `contracts/http/retrieval-call/rc1/` — the `RetrievalPlan` response shape is kept in sync between the Python Pydantic model and the PHP support object by convention only. **This ADR requires that cross-service response synchronisation no longer rely on that convention alone**: an explicit, versioned response schema (e.g. `plan-response-v2.schema.json`, paired with a `contract_version: 2` bump on the plan endpoint) must be added under `contracts/`, additive to and never mutating the meaning of any historical contract version. Exact file/version numbering is an implementation detail to confirm during coding.

### 10. Classifier lineage and fingerprinting

Following the precedent already established for `Embedder` (ADR-0013) and `EvidenceThresholdPolicy` (ADR-0021, `RetrieveWorkspaceEvidence.php:222-229`), the planner gets an equivalent fingerprint capturing provider, model, classifier contract/schema version, prompt/instruction version, and adapter version, attached to evaluation run records so results are reproducible against an exact classifier lineage. Never includes credentials or raw provider responses, consistent with the `rc1` README's existing logging discipline.

### 11. Ground truth

The field-level reconciliations discovered across `PLN-EXP-0001` through `PLN-EXP-0004` — including the 14 previously unrepresentable cases: seven reclassified as calendar-period `VALID_AT_DATE` and seven as `HISTORICAL_REFERENCE` — are a ground-truth change, not a code change, and must be deliberately reviewed and versioned before folding into the production evaluation harness's expectations. Calibration and held-out split expectations are untouched by this ADR and by this reconciliation.

### 12. Migration

Direct pre-production cutover, gated by the sequence below — consistent with how ADR-0013 and ADR-0014 treated `Embedder`/`VectorStore` changes. No parallel old-planner/new-planner production run is needed purely for compatibility: `rc1` is internal-only, and `FixedRetrievalPlanner` (the existing deterministic test double, `apps/ai/app/retrieval/planner.py:216-225`) remains available for unit/integration testing throughout.

### 13. Evaluation sequence after implementation

a. Deterministic/unit/integration verification.
b. Run the complete 42-case / 126-variant engineering benchmark through the real pipeline (planner + Laravel resolution + retrieval, end to end).
c. Inspect classifier and Laravel-resolution correctness first, in isolation from retrieval quality.
d. Inspect retrieval candidate lineage and retrieval metrics.
e. Tune retrieval only using engineering cases, only if evidence from (d) requires it.
f. Freeze retrieval configuration.
g. Threshold calibration using the calibration split only.
h. One sealed held-out acceptance run.

**This ADR does not claim to fix `EXP-0001`'s near-zero retrieval performance.** That result remains an open, separately-tracked problem until step (d) of a corrected full-pipeline run actually measures it under the refined contract.

## Alternatives considered

- **Keep the three-mode contract and rely on further prompt engineering alone.** Rejected — the tested three-mode contract (`PLN-EXP-0001`) achieved 92.86% temporal accuracy, and more importantly **could not truthfully represent the historical-single-state cases** later covered by `HISTORICAL_REFERENCE`: 14 engineering-set cases had no honest three-mode answer at all, regardless of how the prompt was tuned, because the schema itself had no slot for what they were asking.
- **Let the planner resolve historical references or dates directly against document data.** Rejected — would reopen ADR-0018's authorisation/eligibility boundary and let prompt content influence what's structurally treated as authoritative.
- **A second LLM call to resolve calendar periods or historical references.** Rejected — adds latency, cost, and a second non-deterministic surface for a resolution task deterministic code already handles reliably elsewhere in `EligibilityResolver`.
- **Leave `temporal_reference` as an undifferentiated string and let Laravel infer calendar-period vs. historical-reference from its shape.** Rejected — a bounded deterministic grammar cannot fully disambiguate "the 2023 one"-shaped text from context alone; the classifier already makes this distinction linguistically, and discarding it would force Laravel to either re-guess it (reintroducing exactly the risk this ADR removes) or silently prefer one interpretation.
- **Blind leading-article stripping for location normalisation.** Rejected as the *only* mechanism — risks collision with any canonical location name that legitimately begins with an article; the ADR instead states the collision-safety invariant and leaves the specific mechanism to implementation.
- **Union/broaden eligible scope across multiple unrelated resolved locations.** Rejected — no reliable textual signal distinguishes "either site" from user confusion; fail closed instead.
- **Retain the original planner's broad `CLARIFICATION_REQUIRED` surface.** Rejected — clarification-heavy semantic behaviour among `EXP-0001`'s 87 validly-typed-but-temporally-incorrect plans is part of what this contract revision is meant to correct; narrowing planner-side clarification to a single genuine-linguistic-ambiguity case is a deliberate, evidenced scope reduction.
- **Free-text `clarification_reason` on either side of the boundary.** Rejected — both the planner's and Laravel's clarification reasons must be enum-typed and controlled, consistent with ADR-0018's existing discipline of treating clarification as a controlled classification, never an arbitrary string a caller must interpret.

## Consequences

**Positive:** closes a contract gap `EXP-0001` demonstrates was likely the dominant cause of its 0/126 temporal accuracy, without reopening any part of ADR-0018's ownership boundary; preserves the classifier's own linguistic distinction between calendar-period and historical-document references as typed data instead of requiring Laravel to reconstruct it from free text; makes COMPARE's resolution fully explicit and mechanical; keeps all new resolution logic deterministic, testable and narrow-only; adds classifier lineage/fingerprinting and a required formal versioned response contract where only informal convention existed before; keeps ground-truth changes deliberate and reviewed.

**Negative / risks:** all headline numbers are engineering-set-only and not yet validated against calibration or held-out data. The year-qualified-reference routing rule and the "version N means attained-authority position" rule are both judgment calls — the latter is now confirmed to have no existing conflicting definition to worry about, but is still a new normative meaning being fixed for the first time. Failing closed to clarification for multi-location and ambiguous-historical-reference cases is a conservative default that may prove to frustrate real users more than expected if those patterns turn out to be common — accepted as the safer starting position, revisitable with calibration/held-out evidence. This ADR explicitly does not fix, and should not be read as having fixed, `EXP-0001`'s near-zero retrieval recall.
