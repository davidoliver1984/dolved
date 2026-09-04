# R28-S02 provider-free preflight

Status: **Execution preparation in progress; live execution is not authorised.**

R28-S02 uses only the two pre-existing, separately reported populations bound
by R28-S01. It does not use, merge with, or make a quality claim about the
frozen V4 population. Retrieval does not invoke the planner. Generation is the
three-case prompt-injection security regression, not a general generation
quality evaluation.

## Immutable run identities

- retrieval: `R28-S02-LIVE-RETRIEVAL-BASELINE-0001`;
- generation security: `GEN-SEC-LIVE-R28-S02-BASELINE-0001`.

Both wrappers require an exact clean `HEAD == origin/main`, committed policy
and population identities, a fresh output directory, and explicit provider-call
opt-in. Preflight mode cannot call a provider. The two live targets remain
separately opt-in and separately reported; partial execution does not authorise
or imply execution of the other component.

## Enforced safety boundary

- Retrieval: 23 cases / 25 variants; at most two embedding requests and 25
  reranker requests; 500,000 provider input tokens; USD 8; 1,200 seconds; no
  output tokens; concurrency one; one attempt per request.
- Generation security: three generation and three evaluator requests; 100,000
  input tokens; 18,432 output tokens; USD 4 generation, USD 3 evaluation and
  USD 7 total; 600 seconds; concurrency one; one attempt per request.
- Combined approved ceiling: 33 requests, 600,000 input tokens, 18,432 output
  tokens, USD 15 and 1,800 seconds.

The wrappers reject identity, configuration, pricing-lineage, retry, token,
cost and output-path drift. Provider usage is required after execution; missing
usage or cost lineage fails closed. No selective case retry is permitted.

Pricing lineage is the repository's existing OpenAI `gpt-5-mini` pricing
snapshot and the Voyage prices rechecked on 2026-09-04 against Voyage's public
pricing page (`voyage-4-large` USD 0.12 per million tokens; `rerank-2.5` USD
0.05 per million tokens). The committed policy, rather than this note, is the
executable authority.

## Execution gate

No OpenAI or Voyage call has been made in R28-S02. After the harness is committed
and pushed, `make evaluation-r28-s02-preflight` must pass on that exact clean
commit. The resulting exact SHA, policy hashes, calculated bounds and readiness
result must be returned for David's explicit execution decision. Provider-backed
execution must stop until that approval is recorded.
