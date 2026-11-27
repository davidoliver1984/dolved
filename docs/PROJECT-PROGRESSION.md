# Project journey

## Custom RAG foundation

Dolved began as a containerised Laravel, Next.js, Python and Qdrant RAG platform. The
repository records its ingestion, retrieval, generation, governance, evaluation and
observability decisions in specifications, ADRs, task records and engineering journals.
That implementation and its recovery assets remain preserved; later pilot choices do not
rewrite or obsolete the work.

## Bounded single-client pilot

For a faster invitation-only pilot, ADR-0041 selected stock self-hosted Dify behind the
Dolved product and governance boundary. Dolved continues to own identity, tenancy,
documents, administration, audit and presentation. Dify supplies the bound workflow,
retrieval and generation runtime. Quality evaluations and supplementary evidence remain
historical records with their original identities.

## Current position

The hosted pilot is deployed and the bounded security-acceptance phase has completed with
one confirmed live dependency blocker patched and deployed. Hosted access-control checks
then covered the bounded invitation, recovery, role, tenancy, conversation and export
matrix. David has accepted the documented residual risks and remaining fixture-dependent
coverage gaps as sufficient for the invitation-only private pilot. Invitations and
participant onboarding remain separate operational actions; this is not a public-launch or
general production-readiness claim. Start with the
[private-pilot acceptance](operations/dolved-dify/PRIVATE-PILOT-ACCEPTANCE-20261127.md),
[security report](operations/dolved-dify/PILOT-SECURITY-ACCEPTANCE-20261126.md) and
[onboarding checklist](operations/dolved-dify/PILOT-LAUNCH-CHECKLIST-20261126.md).

## Future custom RAG path

Any return to the custom pipeline is a separate bounded programme:

1. Recover and verify the preserved environment and assets using the existing recovery
   guide.
2. Reassess current requirements, risks and architecture before resuming implementation.
3. Resolve known design limitations from general requirements, not by copying sealed or
   historical evaluation answers.
4. Integrate through the preserved capability, event and HTTP contract boundaries.
5. Verify security, compatibility and answer quality on appropriately fresh evidence.
6. Decide adoption separately from the current Dify pilot.

AWS delivery, high availability and multi-client operation remain distinct future choices.
No dates, implementation design or effort estimate is implied here.
