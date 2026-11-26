# Dolved–Dify pilot security acceptance

Date: 26 November 2026  
Outcome: **READY FOR DAVID'S PILOT RELEASE DECISION**

This is a sanitised engineering security review of the invitation-only Dolved–Dify
pilot. It is not a penetration-test certificate, production-readiness claim, public
launch approval or multi-client security assessment. David's release decision remains
separate and no invitation is authorised by this report.

## Scope and boundaries

The review covered the deployed Dolved web/API boundary, its private stock-Dify
integration, authentication and session controls, workspace authorisation, document
delivery, privacy-safe operational evidence, dependency exposure, backup evidence and
the explicitly identified Dolved services on the pilot host. It used source inspection,
provider-faked automated tests and low-volume hosted checks.

It did not test Famvam or any other co-hosted application, run port or IP sweeps, open
sealed evaluation material, inspect real-user private content, ask a model a question,
probe retrieval, request embeddings or alter Dify prompts, models, memory, retrieval,
corpus or evaluation configuration. No synthetic hosted accounts or documents were
created, so there was no synthetic-resource cleanup.

The bounded review took approximately one focused working session. Detailed commands,
safe reproduction notes and raw outputs are retained outside Git in the restricted
evidence package identified below.

## Findings register

| ID | Status | Severity | Affected area | Finding and deployment-specific assessment | Treatment / verification | Release blocker |
|---|---|---:|---|---|---|---|
| SEC-01 | Confirmed, corrected | High | Live mail and HTTP request dependency path | The deployed lock file contained published advisories applicable to invitation/password-recovery mail and request/URL processing. | Only the affected live packages were advanced to patched releases. The focused suite passed 64 tests / 417 assertions, formatting passed, the immutable API image was deployed, and provider-free hosted verification passed. | Cleared |
| SEC-02 | Confirmed | Medium | Browser defence in depth | The HTTPS application response does not yet provide the complete preferred set of browser hardening headers and exposes limited server technology information. TLS and HTTP-to-HTTPS redirection are present; no direct exploit was demonstrated. | Add and regression-test an application-wide response-header policy in a bounded maintenance change. | No |
| SEC-03 | Confirmed hardening gap | Medium | Dolved API container | The API process is root inside a writable container and the Compose service does not yet drop capabilities or declare a read-only root filesystem. No host privilege, published API port or demonstrated escape path was found. | Design and verify least-privilege container changes separately because filesystem/runtime assumptions must be tested. | No |
| SEC-04 | Confirmed, configuration-limited | Medium | Disabled passkey dependency chain | Published advisories remain in packages installed for passkey support. The production Fortify feature set does not enable passkeys, so the affected path is not currently reachable. | Do not enable passkeys until the dependency chain is patched and retested; remove unused packages later if framework compatibility permits. | No |
| SEC-05 | Coverage limitation | — | End-to-end tenancy and invitations | Policy, request and storage tests cover workspace isolation, direct API denial and content delivery. A live two-account cross-workspace exercise was not performed because no authorised synthetic mailbox/account set was required or created. | Complete the named hands-on pilot checks in R30-S04 before release decision. An unexercised control is not recorded as a pass. | Decision item |
| SEC-06 | Known availability boundary | Medium | Single pilot VPS | The invited pilot shares a host edge with another application and has no high-availability claim. The other application was neither inspected nor changed. | Retain the invitation-only scale and documented backup/rollback boundary; reassess hosting before public or multi-client use. | No |

No confirmed authentication/authorisation bypass, cross-workspace exposure, exposed
secret, unsafe public Dify administration surface or practical malicious-upload path was
found in the reviewed boundary. No unresolved confirmed critical or high finding remains.

## Controls and focused verification

- Laravel authentication, administration, document-upload/content delivery, question
  review, usage visibility, feedback and object-storage security tests: **64 tests / 417
  assertions passed**.
- PHP formatting check passed.
- Production JavaScript dependency audit reported no known vulnerability in runtime
  dependencies.
- The patched Composer lock retains advisories only in the disabled passkey chain.
- CSRF enforcement, credentialed-origin policy, secure HTTP-only production cookies,
  login/recovery throttles, server-side workspace policy checks and private source
  delivery were verified by source/read-back and focused tests.
- The public site responded over HTTPS, HTTP redirected to HTTPS, unauthenticated
  workspace access was denied, and the storage boundary did not expose a directory root.
- Dify administration and data services remained private; its nginx endpoints remained
  loopback-only and plugin debug ports were not published.
- Database and object storage had no public host ports. Runtime secret and backup files
  retained restrictive permissions, and the pre-deployment backup checksum verified.
- Tracked secret-pattern review found no private key or live provider credential. Tracked
  example/test environment values are synthetic and remain labelled for development.

## Correction and deployed identity

The security correction is commit
`dd3d640bb59b5f32f3b8be0ba2d6001d502e6e42` on the pilot delivery branch. It changes
only the dependency lock for the affected live mail/request packages. The running Dolved
API, worker and scheduler use immutable image
`sha256:ff0e5545bc002a6ee3c7bb32128dea332aa9cf3e633cc284bf679892fc139405` with the matching
OCI revision. The web image, production data and Dify configuration were not changed.

The general provider-free running verifier did not pass: it stopped on Dify API's retained
`OOMKilled` state from the previously reported memory-limit incident, although that
container was currently running and healthy. Dify was not restarted or altered to clear
the evidence. The correction instead passed focused provider-free checks of the three
updated Dolved services, immutable revision, private backplane, backup checksums, HTTPS
health, unauthenticated access denial and non-public storage boundary. The Dify incident
remains an operational limitation; this phase makes no resource-limit correction claim.

The rollback point is the pre-deployment checksummed backup at
`/srv/dolved-pilot/backups/20261126-security-acceptance-predeploy` together with the prior
immutable API image and source identity recorded in the restricted delivery evidence.

## Residual risk and remaining coverage

The medium findings above require explicit awareness rather than silent acceptance. The
most important remaining checks are the authorised hands-on invitation/role lifecycle,
two-account workspace isolation, source-link permissions and retention/revocation checks.
Those are listed in the [pilot launch checklist](PILOT-LAUNCH-CHECKLIST-20261126.md).

This outcome means that the bounded security phase found and cleared its confirmed launch
blocker and found no other confirmed critical/high issue. It does not complete R30-S04,
R30-S05 or R30-GATE.

## Restricted evidence

Detailed evidence is stored outside Git at:

`~/Desktop/dolved-experiments/dolved-pilot-security-acceptance-20261126/`

The directory is permission-restricted and includes a checksum manifest. Its final
`CHECKSUMS.sha256` identity is
`09db0c9b711c0d7c72a0e3652a5b806448235ed9ea4e512442f071dd7f7e019d`. No credential or
personal content is reproduced in this sanitised report.

