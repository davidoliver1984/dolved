# CAL-EXP-0001 — Evidence threshold calibration

This is the immutable definition for one calibration-only provider pass. It
does not execute calibration and does not select from held-out acceptance data.
Its machine-readable lineage is under
`tests/evaluation/experiment-definitions/CAL-EXP-0001-evidence-threshold-calibration/`.

The exact pushed repository commit freezes the planner, profiles, candidate
counts, equal modality weights, RRF `k=5`, Voyage reranker, factual control
threshold `0.337890625`, final evidence `k=5`, retry/cooldown behaviour,
Laravel timeout, benchmark identity, provisioning lineage, selection policy and
offline replay implementation.

## Isolation

After explicit approval opens the calibration split, a deterministic preparer
must derive two immutable files from Benchmark V2: the 28-case/84-variant
calibration snapshot and matching expectations. `calibration_runtime.sh
prepare` validates their benchmark/split/count metadata, records their exact
SHA-256 digests, and copies them with the independently verified provisioning
record into a private isolated root. The runtime mounts only:

- `/evaluation/calibration/corpus.json` (read-only);
- `/evaluation/calibration/expectations.json` (read-only);
- `/evaluation/calibration/policy.json` (read-only);
- the verified private provisioning record;
- the output run directory.

There is no broad `/evaluation` bind, benchmark source tree, engineering
snapshot/expectations mount, or held-out mount. Both API and AI containers are
checked after creation; visibility of an alternate protected path fails closed.

## One provider pass, then replay

The live command evaluates only the calibration snapshot through the frozen
full pipeline and durably records every observation. The factual run applies
the unchanged control threshold while preserving every pre-threshold reranker
rank, score and EvidenceUnit-coverage match. It never calls a provider once per
candidate threshold.

The provider-free close path compiles those observations into replay input and
evaluates every distinct observed reranker score, the exact control, and an
above-maximum reject-all boundary. For every boundary it recomputes pass/fail,
per-side final `k=5`, controlled outcomes, EvidenceUnit metrics, protected slice
recall and uncredited/unannotated accepted-candidate counts. Stored historical
threshold/final flags are not inputs to the replay decision.

The predeclared policy selects mechanically. No person chooses a threshold after
viewing the curve. Calibration execution and subsequent held-out acceptance are
separate, explicitly approved activities.
