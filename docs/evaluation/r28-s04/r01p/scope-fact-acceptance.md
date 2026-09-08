# ADR-0039 scope-fact acceptance — 2026-09-08

David accepted the planner family-binding boundary and authorised one final i01/i16/i24 pass followed by a local commit. R28-S04/R01P remain in progress; this is not general reliability, pilot readiness or R02 approval.

## Decision and frozen implementation

The diagnosis at `/private/tmp/dolved-scope-binding-diagnosis.0GG4Vt` verified identical deterministic paths and catalogue handling for standalone and compound needs. Whether wording sufficiently identifies a policy is the planner's judgment. Near-identical questions may bind differently; unresolved specificity must clarify rather than guess. David explicitly accepted this limitation before dispatch. ADR-0039's i01 criterion was explicitly revised to permit that safe clarification; historical failed-checkpoint reports remain unchanged. No prompt or application change was made to force convergence in this acceptance task.

Base HEAD: `9210c744be40b2517adf6c97e78de7c21bcdf7a9`. Runtime aggregate, frozen before and verified after execution: `65eb3a1c467bb7f059fb2b6e1842e2f0eced010edcc27e30236e6c2ae1116d30` (951 source files). The local checkpoint includes the accumulated scope-fact implementation, publication follow-through, prior compound-purpose correction, tests and ADR acceptance, not just this documentation delta.

Evidence root: `/private/tmp/dolved-scope-acceptance.zHpSLk`. Full per-case records, dispatch accounting, planner fields and resolved citations are retained there. The existing isolated primary workspace and 982-chunk corpus were reused without re-embedding or index changes.

## Actual once-only results

All three completed and published answers; zero retries. In this pass i01 **matched the family and answered**, rather than taking the accepted clarification fallback. This does not erase the observed model variability or establish guaranteed convergence.

| Case | Actual need resolution | Published result |
| --- | --- | --- |
| i01 | scope_enquiry, matched Asbestos Management Procedure | Recorded scope excludes Harbour View; no operational permission inferred |
| i16 | scope_enquiry, matched Asbestos Management Procedure | Recorded scope includes Oakfield Lodge; safety limitation retained |
| i24 | n1 scope_enquiry, matched Asbestos; n2 retrieve, matched Agency | Separate limited scope fact and supported agency-booking guidance |

i01's full answer:

> The recorded scope of "Asbestos Management Procedure" does not include "Harbour View". This is a statement of recorded policy applicability only; it does not establish what action is safe or required. The reason for this scope is not established by the scope record.

i16's full answer:

> The recorded scope of "Asbestos Management Procedure" includes "Oakfield Lodge". This is a statement of recorded policy applicability only; it does not establish what action is safe or required. The reason for this scope is not established by the scope record.

i24's full answer:

> Recorded policy scope
>
> The recorded scope of "Asbestos Management Procedure" does not include "Harbour View". This is a statement of recorded policy applicability only; it does not establish what action is safe or required. The reason for this scope is not established by the scope record.
>
> Other requested guidance
>
> Agency workers must be booked through an approved framework agency and every booking must be confirmed in writing by email before the shift; the prior allowance for verbal telephone confirmation has been removed and this written-confirmation requirement applies even in genuinely urgent scenarios. Before confirming the shift, the registered manager must also verify the agency worker's DBS check and mandatory training status with the agency (not after arrival).

Each case retains one separate scope citation: family `c6d8a80f-3076-4950-ba36-1ccafb0943b4`, document `bd6da148-fe3f-40fe-90e2-49b4601997c0`, snapshot 226, digest `3b796446fcd75d35c8d5c5b5be3f6b13742f188a02918f99ebce6a71b9575ee2`. Scope-only cases consumed no operational chunk evidence or generation provider request. i24's operational citations are separately resolved in `observations-and-citations.json`; all resolved sources belong to the authorised primary workspace.

## Verification and accounting

- 174 Python tests passed across needs, provider schema, validation retry and generation request/output/transport suites.
- 214 Laravel tests passed / 1,025 assertions; three optional cross-service bridge tests skipped. Suites cover needs retrieval/client/plans, generation, conversation, selection, semantic retrieval and temporal authority.
- Scoped Ruff lint/format, Pint, mypy, JSON parsing and whitespace checks passed. Synthetic transport tests are not proof of language-model consistency.
- Six physical requests: three planner, one query embedding, one reranker, one generation; zero native or validation retries.
- 56,235 input tokens; 2,633 output tokens; estimated USD 0.01851474; 39.13392996788025 seconds, within USD 5 / 90 minutes.

No reruns, new questions, answer-key access, AWS access, re-embedding or push. ADR-0039 is Accepted; broader R01P acceptance remains open. The local commit identity is recorded in the evidence verification record and final handoff, avoiding a self-referential tracked commit hash.
