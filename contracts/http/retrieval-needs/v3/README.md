# Evidence needs plan v3 — applicability scope enquiries

ADR-0039 adds `scope_enquiry` to the existing retrieve/compare operation union.
Active producer identity: `evidence-needs-plan-v3`, `bounded-evidence-needs-v14`,
`structured-chat-needs-v6`. The existing request and signed transport purpose
are retained. V2 historical contracts remain byte-identical; v3 is explicitly
pinned for new needs runs by `bounded-needs-slice-v14`.

Scope enquiries are explicit, grounded planner choices. They do not result from
an empty operational search. Laravel requires a matched single authorised family
and resolved location, selects the authoritative version with its existing
resolver, and reads only its sealed applicability record. An unresolved query
clarifies without operational fallback. No query embedding/reranking is required
for a resolved scope-only need.

The resulting `applicability-scope-fact-v1` is not a generation evidence chunk.
It includes workspace/family/version/snapshot identities, snapshot content and
digest, location and ancestor path, matched ancestor, temporal context and an
`applies`/`does_not_apply` result with its enumerated basis. Unresolved attempts
are diagnostic records (`result: unresolved`) and clarify; they do not invent a
resolvable citation when no version/snapshot was selected.

## Scope-composed answer representation

`scope-composed-answer-v1` is Laravel-owned composition, not an OpenAI response
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

`scope-fact-renderer-v1` uses fixed scope-only wording plus an explicit safety and
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
