# ADR 0004: Use LocalStack 4.14 for Local AWS Emulation

## Status

Accepted

## Date

2026-07-26

## Context

The platform needs local S3 and SQS APIs so document storage and asynchronous
ingestion can be developed without using an AWS account or creating billable cloud
resources.

The roadmap selected LocalStack before its licensing model changed. Starting with
LocalStack 2026.03, current LocalStack for AWS images require an account, an assigned
licence and a confidential auth token, including on the free Hobby tier. The Hobby
tier also excludes local state persistence.

The current `localstack/localstack:2026.07.0` image was tested without a token. It
exited with code 55 because licence activation could not occur. Setting the documented
`ACTIVATE_PRO=0` compatibility option did not bypass the account requirement.

LocalStack 4.14.0, released in February 2026, is the final release of the former
open-source Community image. It supports the S3 and SQS APIs required by this phase,
but its source repository is archived and it will not receive future fixes.

## Decision

Use `localstack/localstack:4.14.0` for local S3 and SQS emulation.

The local environment will:

* bind the LocalStack gateway only to `127.0.0.1`;
* enable only S3 and SQS;
* use standard AWS SDKs in Laravel and Python;
* keep endpoints, credentials, region and resource names configurable;
* use clearly non-production credentials;
* provision buckets, queues and redrive policies through an idempotent initialization
  hook;
* treat LocalStack service data as ephemeral;
* mount `/var/lib/localstack` as `tmpfs` so the Compose model does not imply
  unsupported Community persistence;
* never deploy LocalStack as production infrastructure.

Production AWS uses the same application adapters with real AWS credentials and
without the LocalStack endpoint overrides.

## Alternatives considered

### Current LocalStack for AWS

LocalStack 2026.07 is supported and current. It was rejected for the canonical local
workflow because every developer and CI environment would require a LocalStack
account, assigned licence and confidential token. It would break account-free
one-command onboarding.

### Paid LocalStack plan

A paid plan provides current releases and local state persistence. It was rejected
because routine development of this project should not require a commercial
subscription.

### Separate open-source S3 and SQS emulators

MinIO plus an SQS-compatible emulator would avoid the LocalStack account requirement
and use maintained components. It was rejected for now because it would introduce two
different products, increase orchestration complexity and move away from the roadmap's
single AWS-emulation boundary.

### Direct development against AWS

Using real S3 and SQS would provide maximum fidelity. It was rejected because it would
require cloud credentials, network access, remote resource coordination and potential
cost for routine development and tests.

## Consequences

### Positive

* A clean clone can start local S3 and SQS without any external account or secret.
* Application code uses normal AWS SDKs and crosses over to real AWS through
  configuration.
* Local resources are deterministic and automatically recreated.
* The gateway is not exposed beyond the developer's machine.
* CI can run the emulator without a third-party licence token.

### Negative

* LocalStack 4.14 is archived and receives no security or compatibility updates.
* LocalStack state is intentionally ephemeral; objects and messages disappear when
  its container is recreated.
* The platform cannot rely on newer LocalStack features or behavioural fixes.
* AWS fidelity must ultimately be verified against real AWS in a controlled
  non-production environment.
* The decision must be revisited if the old image becomes incompatible with supported
  Docker platforms or the required AWS APIs.

## References

* [LocalStack 2026 authentication requirements](https://docs.localstack.cloud/aws/getting-started/auth-token/)
* [LocalStack plans and persistence availability](https://docs.localstack.cloud/aws/licensing/)
* [LocalStack 2026.03 account-requirement announcement](https://blog.localstack.cloud/localstack-for-aws-release-2026-03-0/)
* [LocalStack 4.14 source and release record](https://github.com/localstack/localstack)
