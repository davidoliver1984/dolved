# ADR 0037: Require Live-Provider Evidence for Phase 28 Pilot Readiness

## Status

Accepted

## Date

2026-09-04

## Approval

David approved this decision on 2026-09-04.

## Relationship to ADR-0029

ADR-0029 remains historically correct and authoritative for ordinary development
phases: live-provider evaluation is optional, separate, and non-gating for their
mechanical phase closure. This ADR narrowly supersedes that rule only
for Phase 28's `PILOT_READY` decision. It does not rewrite ADR-0029 or require paid
evaluation in every future phase.

## Context

Phase 28 is not merely an ordinary implementation-phase close. It makes an explicit
pilot-readiness decision about the current retrieval and grounded-generation system.
Provider-free contract checks cannot establish the live model and service behaviour
needed for that decision. Treating Phase 28's required live evidence as optional
would therefore allow mechanical completion to be mistaken for demonstrated pilot
readiness.

## Decision

- mechanical Phase 28 completion and `PILOT_READY` are distinct states;
- the Phase 28 pilot-readiness decision requires the approved, identity-bound live-
  provider evidence defined by the R28 protocol;
- missing credentials produce an honest `SKIP`, zero provider calls, and no pilot-
  readiness gate pass;
- a missing or mismatched repository, corpus, population, contract, provider, model,
  prompt, policy, run, attempt, or cost identity fails closed;
- unapproved cost, hidden provider calls, selective retries, or any request, token,
  cost, concurrency, or time-ceiling violation fails closed immediately;
- provider execution remains separately opt-in and requires explicit approval of the
  exact population, protocol, and ceiling before the first call; and
- this exception is confined to Phase 28's pilot-readiness decision. A future phase
  requires paid evidence only if a separately accepted decision says so.

An evidence `SKIP` may coexist with mechanically completed provider-free work, but it
cannot be reported as `PILOT_READY`, as a gate pass, or as fabricated success.

## Consequences

### Positive

- Pilot readiness cannot be inferred from deterministic checks that do not measure
  the live services.
- Credential, identity, and budget failures have explicit, auditable outcomes.
- ADR-0029's ordinary-phase testing strategy remains intact.

### Negative

- Phase 28 cannot reach `PILOT_READY` when credentials or an approved budget are
  unavailable, even if all provider-free work is complete.
- The live run requires bounded paid-provider access and independent review of its
  immutable evidence.

## Alternatives considered

**Silently reinterpret ADR-0029.** Rejected because accepted ADRs are immutable and
its optional/non-gating language is explicit.

**Make live-provider evidence mandatory for every future phase.** Rejected as broader
than the Phase 28 pilot-readiness decision and unsupported by the present need.
