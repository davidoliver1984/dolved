# CAL-EXP-0003 — Post-planner-hardening calibration

CAL-EXP-0003 is a new immutable provider pass over the unchanged compatible
Benchmark V3 calibration population. CAL-EXP-0002 remains immutable historical
evidence of a run that failed closed before threshold selection because two
HTTP-200 planner responses did not satisfy the typed plan contract.

## Sole lineage change

The calibration population, compatibility result, threshold policy, provisioned
corpus, providers and retrieval configuration are unchanged. The only intended
semantic lineage change is the accepted planner adapter correction:

- provider: `openai`;
- model: `gpt-5-mini`;
- contract: `plan-response-v2`;
- prompt: `adr-0022-v1`;
- adapter: `structured-chat-v3`;
- fingerprint: `114789559d7032cefb4e93d1134ce3a4e2234a0db9c26048940cbb1d095758bd`;
- implementation commit: `3c35e42d4883d868e7f9de621aaf66e597cd156a`.

The provider-facing schema constrains valid ADR-0022 temporal combinations.
The application-owned `RetrievalPlan` remains authoritative and fail closed;
there is no semantic repair or retry-to-success.

## Execution boundary

Execution requires a clean worktree whose HEAD equals the pushed `origin/main`
commit. The isolated API and AI services receive only the bound calibration
population and policy artefacts. Engineering and held-out material must remain
physically unavailable. The command refuses a second provider pass once
CAL-EXP-0003 provider observations exist.

Provider execution and provider-free threshold replay require separate explicit
approval after this run definition is reviewed and committed.
