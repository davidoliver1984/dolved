# Evidence needs plan v3 — applicability scope enquiries

ADR-0039 adds `scope_enquiry` to the existing retrieve/compare operation union.
Active producer identity: `evidence-needs-plan-v3`, `bounded-evidence-needs-v20`,
`structured-chat-needs-v7`. The existing request and signed transport purpose
are retained. V2 historical contracts remain byte-identical; v3 is explicitly
pinned for new needs runs by `bounded-needs-slice-v19`.

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
