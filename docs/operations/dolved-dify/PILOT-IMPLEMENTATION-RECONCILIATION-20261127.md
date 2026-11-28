# Dolved accepted-pilot implementation reconciliation

Date: 27 November 2026
Scope: repository reconciliation only
Outcome: **accepted pilot implementation reconciled with `main`**

## Summary

The accepted pilot implementation branch and `main` diverged at commit
`6bf43dd4e9a3d891d44432fc70acae622a2e3282`. The accepted branch retained the complete
implementation lineage, while `main` later received the release-decision documentation,
the specialised runtime-configuration reorganisation and the corrected delivery summary
without that lineage.

The omission was therefore a repository-integration error. It was not evidence that the
hosted participant feedback controls or the other accepted features were absent from the
pilot. The accepted feature commits are ancestors of the recorded deployed web revision
`7b4c1df3b4f1437010601774021f1f2175d766be` and final API security revision
`dd3d640bb59b5f32f3b8be0ba2d6001d502e6e42`.

The complete accepted lineage has now been reconciled with the newer `main` work. The
runtime-configuration relocation, accepted release documentation and corrected
`tasks.json` delivery summary remain intact. Historical evidence and preservation refs were
not rewritten.

After this reconciliation, `main` is the single source of truth for the current integrated
product. The contributor workflow and integration completion check are maintained in
[`CONTRIBUTING.md`](../../../CONTRIBUTING.md); this report remains the historical record of
the reconciliation rather than a competing branch-specific baseline.

## Reconciliation table

| Feature area | Acceptance and implementation evidence | `main` before reconciliation | Recorded deployed revision | Reconciliation result / classification |
|---|---|---|---|---|
| Authentication, invitation acceptance, password recovery, roles and workspace boundaries | Private-pilot acceptance; `e5ac065`; `apps/web/src/components/AuthForm.tsx`, `InvitationAcceptance.tsx`; workspace administration actions, policies and tests | Accepted implementation missing | Present; commit is an ancestor of both recorded deployed revisions | Restored with its UI, API, permissions and tests — **accepted implementation missing from main** |
| Conversation ownership, location selection, Dify memory and continuation safeguards | Private-pilot acceptance; `a58fe27`, `c80c452`, `8b43ded`; conversation actions, resources, migration and focused tests | Accepted implementation missing | Present in the recorded deployed lineage | Restored coherently — **accepted implementation missing from main** |
| Document upload, import, replacement, applicability, withdrawal, deletion and authorised source delivery | Delivery record and accepted hosted boundary; `4b8bcfb`, `5c3f1a8`; document/import actions, controllers, resources and tests | Later accepted refinements missing | Present in the recorded deployed lineage, subject to the accepted hosted fixture limits | Restored without altering the accepted limits — **accepted implementation missing from main** |
| Chat rendering, saved conversations, reload, source presentation, Copy and timestamps | Delivery record; `17332a7`, `87c7335`, `a9490df`; `ChatWorkspace.tsx`, conversation client and tests | Copy/timestamp and later presentation work missing | Present in the recorded deployed lineage | Restored — **accepted implementation missing from main** |
| Participant feedback, reasons/comments and automatic potential-gap signals | Private-pilot acceptance; `c80c452`; `AnswerFeedbackController.php`, `AnswerFeedback.php`, answer-feedback migration/tests, `ChatWorkspace.tsx` and `conversations.ts` | UI, endpoints, persistence, audit and tests all missing | Present in the recorded deployed lineage | Restored as one functioning stack — **accepted implementation missing from main** |
| Restricted Question review | Private-pilot acceptance; `877a094`; Question review controller, models, policy, administration UI, migration and tests | Accepted implementation missing | Present in the recorded deployed lineage, with the accepted fixture-dependent live-check limit unchanged | Restored — **accepted implementation missing from main** |
| Tenant branding, branded invitations and governance correspondence | Private-pilot acceptance; `d1823ad`, `ae077f2`, `9c694e8`; branding service/models/UI, templates and tests | Accepted implementation missing | Present in the recorded deployed lineage | Restored — **accepted implementation missing from main** |
| Telemetry, audit and operational controls | Private-pilot acceptance; `877a094`, `18e0492`, `c5d8a31` and follow-up metric corrections; operational readers, telemetry, deployment definitions and tests | Accepted implementation missing | Present in the recorded deployed lineage | Restored — **accepted implementation missing from main** |
| Security correction and required schema | Security acceptance; `dd3d640`; Composer lock plus six additive pilot migrations | Security lock correction and pilot migrations missing | API security revision and migration-dependent application were recorded as deployed | Restored — **accepted implementation missing from main**; no migration was run during reconciliation |
| Published Dify workflow and production identities | Production Dify configuration and delivery record; workflow configuration records under `deployment/dolved-dify/workflow-config/` | Repository records missing | Workflow v5 and production identities recorded as live | Records restored; app/dataset IDs, credentials and host values remain outside source control — **intentional environment-specific configuration** |
| Specialised E2E/evaluation runtime files | Runtime-configuration organisation commit `8f9840d` | Present at their new runtime paths | Not part of the hosted pilot application | Preserved at `tests/end-to-end/runtime/` and `tests/evaluation/runtime/` — **present or equivalently implemented** |
| R29 complete visual matrix; future custom-RAG, AWS, multi-client and high-availability work | Accepted release decision and current tracker | Intentionally unfinished or future work | Not required by the accepted private-pilot decision | Unchanged — **deferred or superseded work**, not an omission from the accepted pilot |

## Feedback discrepancy

The participant feedback implementation had not moved or been replaced. Its frontend,
endpoint, persistence, audit and permission-dependent review integration were all present in
the accepted lineage beginning at `c80c452`, and all were absent from the divergent `main`
lineage. Restoring only the visible buttons would have been incomplete; the reconciliation
therefore restores the entire accepted stack and its migration and tests together.

## Intentional differences and deployment status

- The deployed web source revision is recorded as `7b4c1df3b4f1437010601774021f1f2175d766be`.
- The final deployed API security revision is recorded as
  `dd3d640bb59b5f32f3b8be0ba2d6001d502e6e42`, which descends from the web revision and
  contains the accepted application lineage.
- Production Dify application, workflow, dataset and metadata identities remain
  environment-specific. Sanitised configuration records and templates are in Git; secrets
  are not.
- No deployment is required to make the reconciled accepted functionality available: the
  hosted application already records those revisions as deployed. This repository change
  does not deploy or restart anything.
- Participant invitations and onboarding remain outstanding operational actions.

## Validation boundary

The reconciliation compares commit ancestry and complete application/configuration trees,
retains the current runtime relocation and tracker wording, and uses only provider-free
repository checks. It does not repeat hosted acceptance, answer-quality evaluation or live
participant actions.
