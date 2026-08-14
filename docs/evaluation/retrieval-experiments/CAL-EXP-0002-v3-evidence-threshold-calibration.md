# CAL-EXP-0002 — Benchmark V3 evidence-threshold calibration

CAL-EXP-0002 is the single authoritative provider pass over the compatible
Benchmark V3 calibration population. Its immutable definition binds the 44
semantic cases, 132 variants, compatibility result and requirements digests,
calibration policy, provider/model lineage, provisioned corpus substrate and
frozen hybrid retrieval configuration before provider execution.

## Isolation and lineage

The runtime receives only the exact calibration corpus, population manifest,
compatibility evidence, threshold policy and verified private provisioning
record. It does not mount an engineering snapshot, held-out snapshot, broad
evaluation directory or benchmark source directory. Both API and AI mounts are
checked after recreation, and execution requires a clean worktree whose HEAD
equals the pushed `origin/main` commit.

Benchmark V3 owns the evaluation cases and expectations. The existing,
unchanged Benchmark V2 provisioning record remains the document/vector
substrate because V3 retains those canonical document identities and sources;
the run records both lineages rather than describing the substrate as V3.

## One provider pass

The live command refuses to start when provider observations already exist.
Each variant is durably checkpointed, but this is interruption recovery rather
than selective rerunning. Planner semantic failures remain strict typed
failures and are not retried to obtain a preferable plan.

## Provider-free replay

After all provider observations are finalised, the close path compiles complete
pre-threshold reranker lineage, evaluates post-provider calibration
compatibility, then replays every distinct observed score boundary, the exact
control threshold `0.337890625`, and an above-maximum reject-all boundary. The
committed policy makes the selection mechanically. No held-out data or further
provider call participates in replay.
