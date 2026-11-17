# Smoke-01 bounded provider-free correction

Smoke-01 remains FAILED; its authorisation is consumed. No resumption, smoke-02,
deployment or sealed confirmation is authorised by this correction.

## Diagnosis and implementation boundary

The current-policy Python adapter used a pricing snapshot identifier as cost_basis;
RecordWorkspaceUsage accepts only provider_reported, estimated, unavailable and
zero_cost_local. The legacy OpenAI adapter already separates estimated from
pricing_snapshot. Correct that producer without changing arithmetic or validation.
The directly related PHP service-transport receipt also used an invalid basis
(unknown_dispatch); preserve its reason and one-request liability, using unavailable.

S1's retained generation receipt reports invalid_output, then orchestration fails
with `InvalidArgumentException: The workspace usage entry is invalid.` The original
raw generation response is absent from its permitted retained records/capture;
the particular validation cause is unrecoverable. Synthetic tests establish no
historical cause. Claude's outcome-label hypothesis remains unconfirmed.

Both Python and PHP duplicated the label-consistency veto. The only execution
consumer, AnswerCurrentPolicyMessage, already derives outcome from answer parts,
scope facts and accumulated gaps/dispositions. Remove only those label vetoes,
retain the schema's enum, and require actual answer/disclosure content. No changes
to prompt, provider, model, retries, capacities, ownership, scope or citations.
An answered label with gaps persists as qualified (or no-answer if no parts/facts),
with all gaps rendered. Structural/citation/content validation still fails closed.

Evaluation-only diagnostics reuse the existing scoped sidecar. Bounded raw output
is untrusted, redacted and never rendered; it identifies future rejection causes,
not semantic correctness. Disabled/foreign-workspace capture writes nothing.

## Cross-service proof

`test_current_policy_receipts.py` uses the real adapter and OpenAI SDK with
httpx.MockTransport. Its deterministic fixture retains actual identity, usage and
result; only per-request correlation and latency are excluded. PHP tests insert
the transport correlation ID and feed those unchanged producer fields through
GenerationClient, real orchestration completion, persistence and RecordWorkspaceUsage.
This is controlled transport, not a live service or live question. Malformed usage
still rolls back answer, evidence, usage and completion atomically. Retained S1
stage receipts are checked separately; the nested checker is not a standalone
recorder entry and must not be counted twice with its planner aggregate.

## Mandatory next-smoke preparation (not executed here)

1. Review this local correction and separately commit/review the previously held
   harness changes. Do not absorb them into this repair commit.
2. Deploy the reviewed API/AI together under separate authority; pin images, source,
   runner and context bindings afresh. No schema migration is required here.
3. Before submission or authorisation consumption, run
   `scripts/evaluation/current_policy_sparse_readiness.py` in a no-egress process
   using the deployed model/revision and exact cache. It denies network access,
   sets Hugging Face offline mode, loads the configured engine and records hashes;
   it submits/encodes no question. Missing files fail preparation: no auto-download.
   The current /opt/fastembed-cache is in the AI writable container layer, not a
   persistent cache mount. Recreation invalidates previous readiness evidence.
4. Bind a fresh, real, writable capture directory for the authorised workspace only;
   preserve smoke-01's destination and checksums. Verify default-off elsewhere.
5. Same six development questions and unchanged criteria, fresh evidence package,
   conservative reservation/spending approval and new one-use authorisation.
   Date-containing current-policy questions remain untested; Next development-mode
   and production-build limitations remain. No sealed release before smoke passes.

Evidence and exact source/failure identities are published locally at
`~/Desktop/dolved-experiments/current-policy-pilot-20261116/smoke-01-correction-01/`.
The full failing isolated baseline is not a green-suite claim.
