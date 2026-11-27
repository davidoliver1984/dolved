# R30-S04 — Verify the bounded pilot

Date: 26 November 2026  
Status: in progress

## Work completed

- Reconfirmed deployed Dolved and Dify image/revision identities and current health without
  clearing the retained Dify OOM incident.
- Exercised generic recovery responses for controlled known and unknown addresses, verified
  issuance for the known controlled account and revoked the session-created reset record.
- Read sanitised aggregate invitation state/audit evidence without opening tokens, recipient
  addresses or message content.
- Read live retention/session configuration and relevant scheduler registrations.
- Reconciled the completed supported cleanup of the isolated six-case Dify app and dataset.
- Reused the already verified 64-test / 417-assertion focused suite rather than repeating it.

## Honest stopping point

The hosted environment has one workspace, no explicitly synthetic workspace and one
verified controlled account. It cannot support the required two-workspace owner/admin/member
matrix through normal workflows. No real identity, private content or production corpus was
modified to fabricate coverage.

R30-S04 therefore remains in progress. The precise live gaps and deterministic evidence are
recorded in
`docs/operations/dolved-dify/PILOT-ACCESS-CONTROL-ACCEPTANCE-ADDENDUM-20261126.md`.
David's invited-pilot release decision remains open.

