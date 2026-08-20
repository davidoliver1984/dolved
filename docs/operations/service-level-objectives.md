# Dolved service-level objectives

The machine-readable source is
`infrastructure/observability/slo-policy.json`. These objectives implement
Accepted ADR-0026 and are explicitly **provisional and unmeasured**.

## Authenticated API technical availability

- Objective: 99.0% over a rolling 28-day window.
- Success: an eligible response that is not a 5xx technical failure.
- Denominator: eligible responses, excluding 4xx expected-rejection traffic.
- Limitation: status code cannot prove whether an individual 4xx was a correct
  rejection. Incorrect rejections remain product-correctness defects and must
  not be described as successful merely because this operational SLI excludes
  4xx responses.

## Conversation technical success

- Objective: 99.0% over a rolling 28-day window.
- Success: `completed`, `retrieval_no_answer` or
  `clarification_required`.
- Failure: `failed`, including `GENERATION_CONTEXT_BUDGET_EXCEEDED`.
- Excluded entirely: `cancelled`.
- Product answer quality is not graded by this SLO. `ANSWERED`, `QUALIFIED`
  and `INSUFFICIENT_EVIDENCE` are all technically completed runs.

## Error budget and calibration

The provisional objective permits 1% technical failure in its rolling window.
V1 uses sustained single-window alert conditions. Multi-window burn-rate
alerting and final latency SLOs require representative production traffic and
are not claimed here. No empty series is converted into a perfect score: the
platform operations page reports `No representative data` or `Unavailable`.
