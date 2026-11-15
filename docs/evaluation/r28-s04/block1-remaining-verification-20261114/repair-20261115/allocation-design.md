# Bounded coverage allocation — decision note, 15 November 2026

**Design only. No allocation implementation or provider call.** David authorised
this assessment during the active 35-hour cap. Other independent package work may
continue. This is not a claim that reservations or relevance scores prove coverage.

## Established losses (not inferred from answers)

Source: retained `../run/cases/26.json` and `28.json`, specifically each need's
`eligibility`, `hydrated`, `reranked`, `final_evidence`, followed by
`needs_final_evidence`/`needs_selection_coverage` and `generation_request`.

| Fact | Trace result |
|---|---|
| 26: control name | Data Encryption Standard v2, document `8fba29d4-7857-488b-ae48-9be6c3799abd`, chunk `c172d391-404f-524f-b645-00deeb087486`, says “'Laptop encryption' renamed to 'Device encryption'”. It survived n1 eligibility/threshold into final per-need rank 5, but was absent from the global five and generation. **Allocation loss.** n1 groups control name and classifier, so one reservation per need is insufficient. |
| 26: cyber route | Cyber Incident Response Plan v3, document `64b73301-2992-4db3-aadd-374dc825df78`, chunk `9aa4ac30-490d-5237-93ba-271c1f07f55a`, says “Contact the regional IT emergency contact for out-of-hours support at a Tier 2 or higher incident.” Supplied to n1/n2; **absent from n3 reranking**, although its document is in n3's retained primary eligibility list. Not an n3 allocation loss. Reuse would require a fresh independent receiving-need check; historical eligibility membership alone is not permission to borrow. |
| 28: site role | Safeguarding Policy (Overarching) v3, document `822e205e-2579-4d1c-812d-7882d886bb97`, chunk `afd325b1-df26-51f5-bbc2-fe2e69b23c1c`, names “The site Safeguarding Champion” and “The organisation-level Safeguarding Lead”. It survived n3 eligibility/threshold at final per-need rank 3; global selection retained n3's two multi-agency passages instead. **Allocation loss**, not evidence absent from the corpus or per-need search. |

`NeedsEvidenceSelection::selectWithCoverage()` reserves one unit per need, then
fills by existing ranks. `includeUnit()` counts need/side/chunk associations as
slots, including duplicate physical passages. It has no sub-aspect support signal.
Neither allocation repair alone nor document diversity would resolve all of 26.

## Existing reranker assessment

The current `RerankRequest` has one query and scores passages for relevance; it has
no requirement decomposition or entailment output. `VoyageReranker::_rerank()`
issues a separate physical request per side, with configured maximum three
attempts and 10-second request timeout (plus bounded cooldown/backoff).
The current query already carries the grounded need and original-question context.
Repeating it supplies no new atomic coverage information.

Scoring separately against grounded sub-aspects could help prioritise them, but
identifying those sub-aspects is the missing step: n1 of 26 and n3 of 28 each
contain two requested facts. Splitting on conjunctions or reserving a family is not
meaning-preserving in general. Exact copied spans establish provenance, not
completeness. A score above today's threshold cannot prove the requested fact is
present, and scores for newly split queries are not automatically calibrated to it.
Thus reranker-only reservations do not adequately address the established mechanism
without an additional semantic assessment. No threshold/ranking tuning proposed.

For A proposed aspects and S sides, reranking adds A×S logical calls and up to
3×A×S provider attempts, not zero-cost reuse. With a proposed six-aspect cap and
two sides, that is up to 12 calls/36 attempts before any outer retry. This is a
design bound, not a current setting. Retained item26 single-side calls took
293/344/298 ms, with 7,343/7,438/7,139 input tokens respectively. Those measurements
do not predict production tails or concurrency. Reranking cost remains unpriced
in these receipts; retain an explicit conservative allowance, not zero dollars.

## Recommended bounded selection design (approval required)

One optional, no-retry selection call using the existing model, **after** normal
retrieval/threshold checks, **before** the five-slot allocator. Proposed new bounds:
at most six extractive requested-aspect spans, at most twenty already-qualified
unique passages, 30,000 total input tokens, 4,096 output tokens, 60 seconds within
the remaining request deadline. If bounds do not fit, preserve existing allocation
and disclose selection unavailable/capacity limited; never silently truncate a duty.
These are proposed settings, not changes made to production.

Input includes the complete original question/turn context, grounded need spans,
typed authority and each candidate's independently allowed need associations.
Output identifies requested-aspect quotations and proposed supporting source
quotations/handles, or explicit unmet aspects. No paraphrase becomes an executable
query. Exact quotation, non-invented identifiers and coverage of every requested
need are deterministic checks; semantic decomposition/completeness remains model
judgment. A whole copied question is not proof that its parts were covered.

Reserve qualifying candidate units per proposed aspect; comparison aspects require
both authorised sides. Choose a deterministic maximum-coverage subset of at most
five physical passages, breaking ties by existing ranks then stable identities.
One passage may serve several aspects only with a validated association for each.
Do not duplicate its text merely to gain an ownership handle. Saturation, missing
support and unresolved decomposition remain explicit unmet coverage, not a claimed
answer. Unused slots may use the existing allocator without altering ranks.

Before reuse, independently recheck the receiving need's workspace, applicability,
family/version/side/interval, ingestion identity and text integrity. Never create a
historical match from a current passage. Generation, temporal-use, citation and
persistence contracts must carry the separate associations; paragraph sharing alone
grants nothing. The eligible-but-unretrieved n3 Cyber passage in 26 is a separate
reuse proposal, not evidence that pure allocation repaired that lane.

Optional typed transport/service unavailability falls back to existing allocation
with the work already spent and limitations recorded. No retry, budget reset or
another selector route. Security, authority, integrity, exhausted budgets/deadlines
and unknown failures retain fail-closed handling. Model-selected passages never
force an answer; generation still must disclose inadequate support.

Using the **retained configured** gpt-5-mini rates ($0.25/M input, $2/M output,
not newly verified current pricing), the proposed maximum call is $0.015692.
Illustrative 8–20k input/1–3k output costs $0.004–$0.011. Additional latency is
unmeasured; plan for one serial call, bounded at 60 seconds, not an assumed subsecond
step. Reserve against the maximum and record actual tokens/time. No live authority
is requested by this note.

## Provider-free acceptance, then separately authorised measurement

Replay the retained candidate sets and exact eligibility projections; distinguish
26's allocation loss from absent n3 retrieval. Controlled selection outputs must
retain the known supporting passages within five slots and name unmet aspects
when that is impossible. Include unrelated higher-ranked passages, conjunctive
conditions, multi-fact passages, cross-turn referents, comparison pairs, rank ties,
saturation, foreign/wrong-version/expired ingestion handles, unavailable selector
and exhausted budgets. Test the actual assembly/ownership/persistence services.
Compare **source-supported facts represented**, not just changed chunk IDs or
reserved slots. Controlled fixtures prove enforcement only; a later approved
measurement must independently grade decomposition, support and answer correctness.

**Decision sought:** approve this one-call coverage selector and independently
validated shared-passage association contract, or retain current allocation with
the two confirmed losses unresolved. No implementation before that decision.
