# Frozen extractive checkpoint — 2026-11-13

No repaired output existed when this selection was written. Thirty distinct
EvidenceUnit-labelled V4 cases, one v1 variant each, then b01-01/v1 and b03-05/v1
as development cases only. Source labels and baseline measurements are separate
from question-only execution inputs and never enter provider requests.

The attempted-search baseline excludes variants that never reached reranking:
primary 0.8813333333333333 (25 variants), comparison 0.46153846153846156
(13 variants with required comparison units). Report new attempted denominators,
the same-case intersection and pre-search stops separately, not a blended metric.
K=5, existing ADR-0020 token-coverage matcher and side ownership unchanged. The
acceptance tolerance is 0.03 per side. No score for the two development cases is
included in the checkpoint gate.

The fixed cases cover comparison, current/historical/relative-date, multi-part,
local/inherited applicability, records, alias/typo and security shapes across all
six batches. Cross-turn context and explicit user examples are provider-free
structural tests, not falsely claimed as live coverage in this single-turn set.

David authorised a checkpoint-only runner stage. The one-use authorization pins
the exact pushed SHA, input/scopes/selection hashes, dispatcher and runner/module
hashes, runtime-source aggregate and configuration/index snapshot. HEAD, actual
remote hold-branch tip and runtime must agree; every existing stage still checks
origin/main. The shared runner retains per-case review gates for this stage.
The pre-existing mixed runner/test edits remain unstaged; the precise executed
runner/module are hash-bound and will be retained with the run.

Budget reservation: USD 3 total, including the two development cases. No selective
reruns. Review usage at each gate before continuing; do not dispatch with less
than USD 0.50 remaining estimated headroom. Report retained pricing estimates and
unavailable stage prices/billing separately rather than calling partial spend a
complete billed total. A failed acceptance criterion stops the checkpoint; no
repair/tuning on the resulting outputs. Main promotion is not authorised until
all fixed acceptance criteria pass.

Provider-free harness proof: 27/27 tests pass, including exact pushed-SHA match,
local/remote/runtime mismatch rejection, consumed authorization rejection before
any dispatch, and an existing stage still rejecting a non-main HEAD.
