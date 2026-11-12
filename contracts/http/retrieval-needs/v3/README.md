# Evidence needs plan v3 — applicability scope enquiries

ADR-0039 adds `scope_enquiry` to the existing retrieve/compare operation union.
Active producer identity: `evidence-needs-plan-v3`, `bounded-evidence-needs-v25`,
`structured-chat-needs-v9`. The existing request and signed transport purpose
are retained. V2 historical contracts remain byte-identical; v3 is explicitly
pinned for new needs runs by `bounded-needs-slice-v20` (three executable needs,
four search lanes, five final evidence slots; scope facts do not consume search lanes).

### Block 1 additive interpretation rules (2026-11-12)

The v25/v9 adapter adds `interpretation-check-v1` after all existing schema,
grounding and catalogue checks and before execution. Its bounded input is only
question turns plus the proposed plan; no catalogue or evidence. It returns only
`accepted`, `clause_omitted`, `meaning_changed`, or `scope_added`. Malformed,
incomplete or unavailable checks cannot accept a plan. There remain two planner
attempts total, with no reviewer retries; a check rejection uses the existing
second attempt with closed stage/field/code feedback, never reviewer prose or
the rejected plan. Exhaustion uses the honest interpretation-failure outcome.
Clarification-only plans do not execute needs and do not invoke the check.
`validation_attempts[].interpretation_check` records version, closed code,
provider/model, tokens, latency and unavailable cost explicitly. Parent usage
includes these physical requests/tokens, so nested receipts are not additive again.
The schema-validated `reviewed_plan` is retained for independent interpretation
assessment, bounded/revalidated by Laravel, never raw completion or reviewer prose,
never supplied as correction feedback, and never an alternate executable plan.

Explicit `last`/`this` month/quarter/year expressions are resolved against the
request's `evaluated_at` in Europe/London in both services. Calendar windows are
start-inclusive/end-exclusive; existing interval wire values retain inclusive
last days. Source quotes and turn IDs remain verbatim. Relative dates cannot be
encoded as duration/quantity; unknown event dates are not inferred. Python receives
application-computed calendar values; PHP independently verifies them against
the same request anchor and resolves relative authority windows in London time.
The shared `relative-calendar-examples.json` freezes timezone/leap/year boundaries.

Historical v24/v8 behaviour (superseded by the approved addition above):

The v24/v8 adapter retains the maximum two attempts and every existing validator.
Only the second eligible attempt receives `validation_feedback` containing the
safe diagnostic `stage`, `field`, and `code`. Rejected output, exception messages,
receipts and candidate answers are never added to the request. A fixed instruction
requires preserving every requested part. This is not a completeness validator:
schema-valid plans can omit a requested clause on either attempt. Independent
clause review is mandatory in the bounded development checkpoint; silent omission
fails it. No new coverage mechanism or provider retry is introduced.

Deploy the v24 producer, generated schemas and Laravel consumer together. Authority
`before`/`after` retains a quoted day/month/year and the relation; Laravel resolves
the version immediately before the period starts / immediately after it ends.
Day boundaries exclude that whole named day. For example, before July is the instant
before 1 July, not a lookup within July; after July starts at 1 August. The existing
authority timeline, approvals, applicability and workspace checks remain authoritative.
Follow-ups may quote a calendar selector from an earlier authorised user turn and
the relation from the current question span. That increment added no relative-calendar arithmetic.

Fact references also accept `duration` and `quantity`, retaining exact user wording.
These are policy-rule context, never `fact_date` filters; duration requires an explicit
duration unit. Existing calendar precision/grounding validation remains strict.

The existing maximum two completed-output validation attempts now also covers invalid
fact/reference grounding. Exhausted completed invalid output produces an
`interpretation_failed` retrieval outcome with reason `planner_validation_failed`
and an honest controlled no-answer, not an invented missing-date clarification.
Transport/provider failures retain their existing failure path. Validated user ambiguity
and capacity overflow retain their separate clarification reasons.

Per-need `selection_coverage` records eligible/selected/budget-cut complete units in
application lineage and bounded diagnostics. A compare unit is a same-family pair;
ordinary units are passages. Allocation, minimums, order, thresholds and FINAL_K=5
are unchanged. A qualified unsupported aspect linked to a budget-cut need discloses
that the selected evidence is incomplete, without concluding the source lacks the fact.
This application-owned disclosure persists with the existing unsupported-aspect fields.

Scope enquiries are explicit, grounded planner choices with a required
`scope_question` of `membership` or `breadth`. They do not result from an empty
operational search. Laravel requires a matched single authorised family and
selects the authoritative version with its existing resolver. Membership also
requires a resolved location; breadth may request the full recorded extent
without one. Only the sealed applicability record is read. Ambiguous family or
location references clarify without operational fallback. An unavailable
authoritative scope record yields a controlled no-answer. No query
embedding/reranking is required for a resolved scope-only need.

The resulting `applicability-scope-fact-v1` is not a generation evidence chunk.
It includes workspace/family/version/snapshot identities, snapshot content and
digest, location and ancestor path, matched ancestor, temporal context and an
`applies`/`does_not_apply` result with its enumerated basis. Unresolved attempts
are diagnostic records (`result: unresolved`); they do not invent a resolvable
citation when no version/snapshot was selected. The versioned
`applicability-scope-breadth-fact-v1` instead carries every location ID/name and
ancestor relationship in the selected snapshot, plus its digest. Its renderer
states the recorded extent, or universal scope, without inferring historical
change from one snapshot.

## Disputed claims are not applicability

An optional `claim_under_test` has its own `claim-under-test-v1` identity,
neutral relation type, and exact user-turn quote. It cannot supply a truth value
or document ID. Laravel checks the quote against the authorised turn, includes
it only as retrieval context, and does not send it through location resolution.
A genuine site constraint can coexist in `applicability` and remains a hard
eligibility gate. Generation receives the claim alongside evidence owned by
that need and must confirm, contradict, or report insufficiency from cited
authorised evidence; absence of a claimed value alone is not a contradiction.

## Scope-composed answer representation

`scope-composed-answer-v2` is Laravel-owned composition, not an OpenAI response
schema. Existing operational `generation-result-v2` requests/results remain
unchanged. Scope facts never enter that provider request. Operational generation
uses only its standalone planned aspects and their eligible evidence, retaining
the existing validators, request retries and chunk citations.

The composed answer stores `scope_facts` separately from operational answer parts
and `aspect_results`. Each scope fact has its own `need_id`. Conversation answer
resources and answer-completed events expose these as `scope_citations`; they
are never inserted into the legacy chunk-citation array. Source resolution uses
the existing authorised family-version metadata endpoint in `source_route`,
selecting `document_version_id` and its applicability record; the persisted
snapshot ID/content/digest preserves exactly the cited historical record.

`scope-fact-renderer-v2` uses fixed scope-only wording plus an explicit safety and
missing-rationale limitation. It never interpolates the user's desired conclusion.
Mixed text labels recorded scope separately from other requested guidance, with
no model-written bridge. If the other need lacks operational evidence, it remains
an explicit gap. Scope-only answers incur zero generation requests; provider/model
fields retain the configured lineage but usage records zero execution.

Deploy the additive generated-answer `scope_facts` column before activating the
v3 planner profile. Old answers remain unchanged. Rollback must disable new v3
runs first and retain the column and existing scope facts as historical evidence;
dropping it would destroy citation history. Do not reinterpret in-flight v2 pins.

The API now exposes scope citations, but no new browser citation-card design is
claimed by this bounded backend correction. Independent verification must inspect
the stored typed records as well as the rendered answer.

## Relative-calendar arithmetic ownership (2026-11-12)

At provider adaptation, a grounded authority/fact-reference quote containing one
explicit `last|this month|quarter|year` expression is canonicalized by the
application using `evaluated_at` in Europe/London. The model's value/precision are
replaced; its intent/kind, source and other fields are not. Laravel independently
derives the same values before consuming the plan. Existing schema and grounding
validators remain strict, including after presentation ordering (which retains
the evaluation anchor). No schema field, prompt, retry ceiling or authority rule
changes. Boundaries remain start-inclusive/end-exclusive as computed by the shared
calendar rules. Multiple expressions, even repetitions, are not one period.
Ungrounded quotes fail; event dates and elliptical follow-ups are never inferred.
