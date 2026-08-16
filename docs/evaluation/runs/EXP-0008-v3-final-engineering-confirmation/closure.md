# EXP-0008 closure

`EXP-0008-v3-final-engineering-confirmation` is the accepted final
engineering-only confirmation for the current planner and retrieval block. It
executed once from repository commit
`a21431bc0f9137978f3c4d082619954f8814bd9d` over ten Benchmark V3 engineering
cases and 31 variants.

This result is development and regression evidence. It is not calibration,
sealed held-out acceptance, customer-corpus evidence, or an unseen-
generalisation claim.

## Accepted result

| Metric | Result |
|---|---:|
| Case-first Recall@K | `0.9667` |
| Clean-upstream Recall@K | `1.0000` |
| Benchmark precision@K | `0.2100` |
| MRR | `0.9333` |
| nDCG@K | `0.9157` |
| Planner correctness | `29/31` (`0.9355`) |
| Eligibility correctness | `30/31` (`0.9677`) |
| Outcome correctness | `30/31` (`0.9677`) |

All 36 correctly scoped expected EvidenceUnits remained present at every
downstream stage: Dense, Sparse, union, fusion, reranking, threshold and final
evidence. EXP-0008 therefore demonstrated no Dense, Sparse, fusion, reranker,
threshold or final-K defect for that population.

## Residual planner risk

Two residual content/event-time versus document-authority-time classifications
are accepted as known risk:

* `v3.safeguarding.current.body-map / colloquial` produced
  `CLARIFICATION_REQUIRED` instead of `CURRENT`, losing one expected
  EvidenceUnit.
* `v3.training.current.fire-marshal-refresh / contrast` produced `COMPARE`
  instead of `CURRENT`, but lost no expected EvidenceUnit.

Fail-closed behaviour remained intact and the runner performed no semantic
retry-to-success. Planner and retrieval tuning must not be reopened merely to
improve this engineering benchmark. Revisit only for an introduced regression,
a material recurring real-corpus pattern, a systemic sealed-acceptance finding,
or an explicitly approved future planner architecture stage.

## Frozen lineage

* Population digest:
  `d24d61a9aef55c8d3ca8d6609fbb44683665acc22e8d4f9652f00cb4d575d4c3`
* Planner: OpenAI `gpt-5-mini`, contract `plan-response-v2`, adapter
  `structured-chat-v3`, prompt `adr-0022-v5`
* Planner fingerprint:
  `b18ce9cfcb769bbe2c2d28e74ba9b1ffa90a62c887de7e9b04d595cc6a1cf690`
* Active hybrid generation: `289ccffe-2264-4867-aa1e-d8eb1af43300`
* Dense generation: `8da556fc-2839-455b-bf40-6b5802678b43`
* Sparse generation: `5e25eb0a-cff1-4f4d-9246-9ca186d892f2`
* Retrieval configuration: dense K `40`, sparse K `40`, equal-weight RRF
  `k=5`, fusion K `15`, reranker K `15`, threshold `0.337890625`, final
  evidence K `5`

The threshold remains the CAL-EXP-0003 exact-lineage calibrated value. It is
not represented here as sealed-held-out accepted, universally valid, or
production-promoted.

## Artefact storage

The consolidated authoritative observation file is stored in Git using the
existing repository convention. It is `28,586,791` bytes with SHA-256
`94addfcf4f206483fb525a9dce1e4c6b0f3db745aa0b8309212d1514344fd56d`,
below GitHub's single-file limit. `checksums.sha256` binds it together with the
result, reports, configuration, comparison, provisioning mapping and run
manifest.

The 31 durable per-variant recovery observations are deliberately not
duplicated in ordinary Git. Their authoritative source copies remain in the
existing immutable local evaluation run history at:

`/private/tmp/rag-platform-exp0008/runs/EXP-0008-v3-final-engineering-confirmation/observations/`

They total `70,590,884` bytes. The committed
`per-variant-observations.sha256` inventory records every filename, byte size
and SHA-256; that inventory's own SHA-256 is
`cdb5cba7513bf366760aa925842424c2f95b3502c38025ddd068b95f4d660a33`.

The consolidated file was atomically finalised by
`EngineeringBenchmarkExperimentProgress::finaliseFromCheckpoints()` from the
31 validated checkpoint `observation` payloads in the immutable experiment
order. A provider-free set comparison confirmed that sorting both sources by
`case_id` and `variant_id` produces byte-identical canonical JSON with SHA-256
`70cd265348ff06e6de9b81805115f79bd61e21dca3d843838b68e68cd9a58d6a`.
All 31 variants therefore remain represented in the committed authoritative
consolidated artefact without retaining a second 70.6 MB Git copy.
