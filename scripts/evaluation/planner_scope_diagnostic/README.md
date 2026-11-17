# Planner scope diagnostic — preparation tooling

This is not a conversation/browser runner. `invoke.php` boots the real API,
authorises the fixed actor/workspace through `BuildAuthorisedKnowledgeScope`,
obtains the server current-policy pin, and calls `RetrievalClient::planNeeds`.
It observes RequestSending identities/signing booleans without changing headers,
plans, validation or native retries. The HTTP body hash and catalogue hash are
recorded, never authentication headers. Each standalone process is one invocation.

The production path reads membership/generation/catalogue data; it does not
record conversations, messages, answers or WorkspaceUsageEvents. AI retains its
existing process-local authentication replay cache, safe telemetry and evaluation
sidecars. These controls are not disabled. Usage comes back in the planner receipt;
nested attempt/checker receipts are not added twice.

`control.py` reuses the existing runner's digest, durable-write and reservation
helpers. The fixed 18-slot manifest is immutable. `commands.py` provides the real
read-only verification and dispatch adapters. Only explicit `execute` can call
the planner; `verify` additionally requires empty capture and absent authority/run.
No worker is started by any mode.

Before release, freeze source, dependencies, deployment, manifest and capture
identities in the external package. A separate David-approved authority must name
that binding hash; pass its SHA-256 in `CURRENT_POLICY_AUTHORIZATION_SHA256`.
No authority is included in this repository. Consumption/start markers and a
per-invocation maximum reservation precede any dispatch. Unknown dispatch stays
unknown and stops; never resume or resubmit. Complete record/attempt capture is
required even for safely recorded rejection outcomes. Truncated/missing capture
is an integrity halt, not evidence that scope validation succeeded.

Time: 60 minutes from the first pre-dispatch clock, capped by the approved calendar
deadline. Each invocation must fit two configured PHP request windows plus 30
seconds bounded dispatch/export overhead. Native request settings are unchanged.
If the outer process times out, retain maximum liability and halt uncertain state;
do not issue another request or claim provider cancellation. Worst-case durations
do not guarantee all 18 slots fit within an hour; operational stops leave the
remaining fixed slots untouched.

Provider-free checks: `test_control.py`, `test_capture_adapter.py`, and
`InvocationTest.php` (actual Laravel/signing, HTTP fake, ephemeral SQLite).
Existing AI scope/capture tests exercise native correction and quarantine through
controlled transport. Use isolated no-network containers, no provider credentials
or evaluation data. None of these tests dispatches the released questions.
