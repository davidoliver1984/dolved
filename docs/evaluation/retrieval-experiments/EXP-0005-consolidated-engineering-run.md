# EXP-0005 consolidated engineering run

`EXP-0005-adr0022-v2-consolidated-engineering-baseline` is the immutable
measurement boundary for the complete 42-case, 126-variant Benchmark V2
engineering population after the accepted ADR-0022-v2 planner and benchmark
corrections.

The run freezes the existing application configuration: OpenAI `gpt-5-mini`
with `plan-response-v2`, `structured-chat-v3` and `adr-0022-v2`; Voyage
`voyage-4-large`; SPLADE++ `prithivida/Splade_PP_en_v1`; equal-weight RRF with
`rrf_k=5`; Voyage `rerank-2.5`; evidence threshold `0.337890625`; and final
evidence K of 5.

This is a diagnostic engineering run. It does not recalibrate or promote the
threshold, tune retrieval, consume calibration or held-out cases, or alter
application behaviour. The runtime mounts only the exact engineering corpus,
engineering expectations, immutable provisioning record and output locations.
The API and AI containers fail preflight if broad evaluation, calibration or
held-out paths are visible.
