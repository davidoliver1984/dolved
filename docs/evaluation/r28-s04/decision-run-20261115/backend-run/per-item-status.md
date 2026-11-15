# Per-item execution status (ungraded)

| Item | Cohort | Execution | Production status | Service calls | Sidecar statuses |
|---|---|---|---|---:|---|
| O1 | fresh | recorded | retrieval_no_answer | 6 | {'accepted': 1, 'rejected': 1} |
| O2 | fresh | recorded | completed | 6 | {'accepted': 1, 'rejected': 2} |
| O3 | fresh | recorded | retrieval_no_answer | 6 | {'accepted': 1} |
| O4 | fresh | recorded | retrieval_no_answer | 6 | {'accepted': 1} |
| O5 | fresh | recorded | retrieval_no_answer | 4 | {'accepted': 1} |
| O6 | fresh | recorded | retrieval_no_answer | 4 | {'accepted': 1} |
| O7 | fresh | recorded | completed | 8 | {'accepted': 1, 'rejected': 2} |
| O8 | fresh | recorded | retrieval_no_answer | 4 | {'accepted': 1} |
| O9 | fresh | recorded | retrieval_no_answer | 4 | {'accepted': 1} |
| O10 | fresh | recorded | retrieval_no_answer | 4 | {'rejected': 1, 'accepted': 1} |
| O11 | fresh | recorded | retrieval_no_answer | 4 | {'accepted': 1} |
| O12 | fresh | recorded | retrieval_no_answer | 6 | {'accepted': 1} |
| H1 | fresh | recorded | completed | 9 | {'accepted': 1, 'rejected': 1} |
| H2 | fresh | dispatched_unfinalised | completed | 8 | {'accepted': 1} |
| H3 | fresh | not_dispatched | — | — | — |
| H4 | fresh | not_dispatched | — | — | — |
| C1 | fresh | not_dispatched | — | — | — |
| C2 | fresh | not_dispatched | — | — | — |
| C3 | fresh | not_dispatched | — | — | — |
| C4 | fresh | not_dispatched | — | — | — |
| M1 | fresh | not_dispatched | — | — | — |
| M2 | fresh | not_dispatched | — | — | — |
| M3 | fresh | not_dispatched | — | — | — |
| M4 | fresh | not_dispatched | — | — | — |
| K1 | control | not_dispatched | — | — | — |
| K2 | control | not_dispatched | — | — | — |
| K3 | control | not_dispatched | — | — | — |
| K4 | control | not_dispatched | — | — | — |
| R19 | regression | not_dispatched | — | — | — |
| R24 | regression | not_dispatched | — | — | — |
| R26 | regression | not_dispatched | — | — | — |
| R28 | regression | not_dispatched | — | — | — |
| R29 | regression | not_dispatched | — | — | — |
| G3 | regression | not_dispatched | — | — | — |

Sidecar statuses are observed attempt receipts, not reconstructed provider attempt counts. Raw per-stage attempts/retry fields, token usage and latency are retained in service-receipts.json; absent fields remain unknown. H2 production completed but its runner capture/integrity finalisation failed. No correctness verdict is assigned.
