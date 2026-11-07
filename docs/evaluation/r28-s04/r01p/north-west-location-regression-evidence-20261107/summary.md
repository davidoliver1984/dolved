# North West location correction — bounded regression evidence

The 17 predeclared questions were run once each, in order, through the real primary-workspace production conversation path, with only that workspace opted into evidence needs. All have full production traces, persisted displayed text, and `needs_pipeline_exercised: true`; no execution exception occurred. This package is a known-case regression, not independent scoring. The source aggregate and HEAD in `code-freeze.json` were unchanged before and after. The checkpoint-19 archive remained byte-identical. The approved `North West` alias was added only to the isolated primary-workspace registry by the separately recorded fixture; no foreign-workspace alias was added.

**The predeclared clean gate did not pass, so this correction was not committed or pushed.** r19 resolved the registered North West Region and published a Key Holder Procedure scope fact, rather than failing `unresolved_location_reference`. r17, however, now came back from the planner with `subject_status: topic`, no family ID, and generic applicability; it clarified at `ambiguous_document_subject` before the location resolver could use the new alias. In the original r17 trace the planner had produced a matched Key Holder Procedure family and named `North West` applicability. One sample cannot prove whether that judgment difference is caused deterministically by the new instruction or by model variance. It is nevertheless a real failure of the agreed clean live gate; no further tuning or rerun was attempted.

r08 remained a safe `ambiguous_document_subject` clarification, confirming its separate issue was not solved here. r16 changed from clarification to an answered/evidence-found path, without an execution failure; this is an observed classification, not a correctness judgment. The six m-series tenant canaries retained their prior `evidence_found`/`answered` classifications. l14 and l19 retained theirs; l25 retained `insufficient_evidence`. l23 and l26 changed to evidence-found outcomes. n17 and n21 retained scope-fact answers and their already-documented breadth limitation was not altered. No control exposed a foreign-workspace location match, and the provider-free resolver test rejects a foreign-only alias.

| Case | Retrieval | Generation or controlled outcome |
| --- | --- | --- |
| r08 | clarification_required | ambiguous document subject |
| r16 | evidence_found | answered |
| r17 | clarification_required | ambiguous document subject |
| r19 | evidence_found | local scope-fact answer, North West Region |
| m01, m05, m06, m09, m10, m11 | evidence_found | answered |
| l14 | evidence_found | answered |
| l19 | evidence_found | qualified |
| l23 | evidence_found | answered |
| l25 | insufficient_evidence | controlled no-answer |
| l26 | evidence_found | qualified |
| n17 | evidence_found | local scope-fact answer |
| n21 | evidence_found | scope fact plus generated operational answer |

The provider-free Python needs/schema suite passed **115 tests** (one host-mounted pytest cache warning); the focused Laravel needs/client/orchestration suite passed **491 assertions**, and the semantic retrieval suite passed **240 assertions** (with host-backed file I/O notices; no assertion failures). Scoped Ruff lint/format, mypy with a container-local cache, PHP syntax, Pint, JSON parsing, and `git diff --check` passed. An initial Laravel run failed on an obsolete assertion that expressly required rejecting a stripped scope suffix; that assertion was updated to the newly approved behaviour. An initial mypy run hit a host-cache internal error and passed with an isolated cache. Neither diagnostic command made a provider call.

Recorded provider use: 17 OpenAI planner requests, 12 OpenAI generation requests, 15 Voyage embeddings, 16 Voyage rerankings: **60 physical requests/attempts**, zero validation or provider-native retries. Stage-summed input tokens: **619,260**; output tokens: **17,793**. Elapsed time: **309 seconds** (5m09s), within 90 minutes. Known estimated cost: **USD 0.06514287** (generation 0.06508215, Voyage embedding 0.00006072). Planner and reranker cost fields are unavailable, so this is not a complete or actual-cost total and the full USD 5 frame cannot be independently reconciled from provider cost receipts.

`run/cases/*.json` contains exact questions, plan/binding/applicability, eligibility, search/hydrated/reranked stages reached, scope facts, evidence, generation, citations, usage and displayed text. `run/dispatch-records.ndjson` has one dispatch and one record event per case. `checksums.sha256` inventories every package file except itself. No selective rerun, code change during execution, AWS call, commit or push occurred.
