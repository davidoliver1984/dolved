# Session Journal: R18-S01 — Define Conversation Domain

## Date

2026-08-18

## Session mode

Architecture and documentation only. No application code, migrations,
contracts, provider adapters, prompts, benchmark material or provider calls
were introduced.

## What happened

R18-S01 defined the persistent conversation and streaming architecture in
accepted ADR-0024. The decision separates tenant-owned visible conversation
history from durable `GenerationRun` execution, preserves ADR-0023's grounded
answer and evidence-snapshot authority, and makes browser delivery a projection
of application-owned state rather than the owner of provider work.

An implementation-readiness review then found that the initially accepted text
did not map all eight existing ADR-0018 retrieval outcomes into conversation
semantics. With explicit repository-owner approval, a dated, same-day,
pre-implementation clarification completed that mapping without changing the
accepted Laravel/Python ownership or streaming architecture. This narrow
clarification is not a precedent for silently editing Accepted ADRs; substantive
future decision changes still require a superseding ADR.

## Decisions recorded

- `Conversation`, visible `Message` and durable `GenerationRun` have distinct
  responsibilities and tenant-owned lineage.
- Assistant message kinds are `GROUNDED_ANSWER`, `CLARIFICATION` and
  `NO_ANSWER`, each backed by the authoritative record specified by ADR-0024.
- Laravel owns identity, authorisation, tenancy, contextualisation orchestration,
  retrieval, eligibility, controlled outcome handoff, generation assembly,
  validation, persistence and browser SSE projection.
- Python owns provider-neutral contextualisation/generation capabilities and
  provider-specific execution behind authenticated `rc1` calls; it never
  retrieves or resolves authority, applicability or tenancy.
- Only `EVIDENCE_FOUND` reaches generation. Every other existing retrieval
  outcome short-circuits through the explicit ADR-0024 mapping.
- `RETRIEVAL_FAILED` remains exclusively an operational retrieval failure.
  `TEMPORAL_SCOPE_UNRESOLVED` remains a separately preserved retrieval outcome
  that independently fails the conversation run.
- Provider-native streaming candidates and Laravel chat-stream events remain
  provisional until final generation validation succeeds. `generation.answer`
  remains the complete-result fallback.
- Generation execution is independent of browser connections. Laravel queue
  framework/configuration support exists today; application-owned queued
  generation jobs do not and belong to later implementation.

## Documentation reconciliation

- Added ADR-0024 to `docs/adr/README.md`.
- Replaced the Stage 18.1 planning stub in `IMPLEMENTATION_GUIDE.md` with the
  accepted architecture and factual completion evidence.
- Corrected Stage 18.2's stale flow so Laravel, not Python, retrieves evidence.
- Recorded the Phase 18 architecture boundary and current status in
  `PROJECT_ROADMAP.md`.
- Marked R18-S01 complete and advanced `tasks.json` to R18-S02.
- Did not update `PROJECT_JOURNEY.md`: this architecture-only stage introduces
  no user-visible capability yet; the Phase 18 story belongs there once working
  conversation behaviour exists.

## Verification

- Confirmed ADR-0024 remains `Accepted` and its post-acceptance clarification is
  visibly dated 2026-08-18.
- Confirmed the approved retrieval-outcome mapping and Laravel/Python ownership
  boundary were not altered during reconciliation.
- Confirmed the queue wording matches the repository: queue configuration is
  present, while no application-owned `ShouldQueue` generation job exists.
- Validated `tasks.json` as JSON and checked documentation references and stage
  line ranges.
- Ran the repository documentation checks applicable to this boundary and
  `git diff --check`.
- Confirmed no application behaviour, retrieval/planner configuration,
  threshold, calibration, benchmark or held-out material changed.
- Left unrelated local `docs/adr/NOTES.md` and `docs/assets/**` untouched.

## Next step

R18-S02 may implement the accepted chat-orchestration boundary. It must not
re-decide the controlled retrieval mapping, move retrieval into Python or begin
R18-S03's browser streaming implementation.
