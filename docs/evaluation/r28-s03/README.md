# R28-S03 V4 corpus materialisation

`R28-S03-V4-CORPUS-MATERIALISATION-0009` materialises the frozen V4 corpus
through the real authenticated ADR-0034 import workflow in a dedicated local
Docker project. It uses only the deterministic provider profile. It does not
consume the R28-S04 live Voyage allowance and makes no OpenAI, Voyage or AWS
request.

The four governed scopes remain separate:

- 300 primary version documents in the primary evaluation workspace;
- 12 foreign-tenant documents in a different workspace;
- six additional prompt-injection documents in a security-test workspace;
- 13 negative/import fixtures exercised as validation, rejection, matching,
  interruption and replacement cases without ordinary searchable promotion.

Run preparation and execution are performed by
`scripts/evaluation/run_r28_s03.sh`. The script fails unless the repository is
clean, `HEAD` equals `origin/main`, the frozen archive identities match, the
runtime has no provider credentials, and the materialisation project name and
database are isolated.

The durable result and checksums are written here only after the single run
finishes. A failed run remains evidence and is not selectively repaired or
rerun.

Attempt `0001` failed before any ImportBatch or document was created because
the isolated organisation provisioner treated the optional foreign-tenant
`aliases` member as required. The database evidence was three isolated
workspaces, zero import batches and zero documents. Attempt `0002` is a new
immutable run identity after the provider-free provisioner correction; it is
not a selective retry of any corpus item.

Attempt `0002` also failed before any ImportBatch or document was created. A
valid corpus manifest entry explicitly uses a null effective date, while the
materialisation harness applied its governed default only when the field was
omitted. Read-only database evidence again showed three isolated workspaces,
zero import batches and zero documents. Attempt `0003` is a new immutable run
identity after making the existing default apply consistently to null and
omitted values and binding result identity to the active run definition.

Attempt `0003` reached the first real ImportBatch and created 25 staged item
identities, then failed before uploading or promoting a document because PHP
serialised its empty signed-upload header map as an empty JSON list. The
database contained one batch, 25 items and zero documents. Attempt `0004` is a
new immutable identity after allowing only that empty-list wire representation;
non-empty upload headers must still be a named map and otherwise fail closed.

Attempt `0004` materialised and indexed 209 primary documents before the
application correctly rejected the second e-bike proposal version. Both frozen
versions omit an effective date, so the harness had assigned both the same
default even though v1 declares a supersession date. The promotion exhausted
after three recorded failures; it was not selectively retried. Attempt `0005`
uses the general frozen-manifest rule that a null-dated version with a declared
supersession date is placed one day before that boundary. Null-dated entries
without a supersession date retain the existing deterministic default.

Attempt `0005` materialised and indexed all 300 primary documents, producing
982 canonical chunks, then failed before governance transitions or either
separate tenant was materialised. The harness indexed the canonical document
administration response using the import-workflow field name `filename`; that
response truthfully exposes `source_filename`. All 300 primary documents
therefore remained draft. No corpus item was selectively rerun. Attempt `0006`
uses the canonical response field and begins from a fresh isolated runtime.

Attempt `0006` again materialised and indexed all 300 primary documents and
982 canonical chunks, then the application correctly rejected the first
governance transition because the harness supplied a prefixed idempotency
value rather than the UUID required by the canonical request contract. All
documents remained draft and neither separate tenant had begun. Attempt
`0007` supplies a plain UUID and begins from a fresh isolated runtime.

Attempt `0007` materialised and indexed all 300 primary documents and 982
canonical chunks. During the historical governance replay, 93 documents were
approved and three were withdrawn before the application correctly rejected
an authority-start collision; 204 remained draft and the separate tenants had
not begun. The harness had replayed historical approvals at the current wall
clock, so past-effective versions in one family could receive the same
authority start. No corpus item was selectively rerun and no provider or AWS
call occurred. Attempt `0008` moves governance replay to an isolated E2E-only
command that calls the real approval and withdrawal actions at the frozen
manifest dates. This preserves historical authority semantics without changing
production governance behavior.

Attempt `0008` materialised and indexed all 318 searchable documents across
the three isolated workspaces, producing 1,000 canonical chunks. It then
failed closed at the exact negative-fixture inventory check before governance
replay. The oversized-file simulation is represented in the governed manifest
by `oversized-file-simulation.json`, while the harness necessarily submitted a
simulated oversized PDF request and incorrectly used that request filename as
the outcome identity. The database retained 19 batches, 331 items, 329 verified
preflight items, two rejected preflight items, 318 indexed documents and all
318 documents in draft. No selective rerun, provider call or AWS call occurred.
Attempt `0009` records the outcome under the governed fixture identity while
retaining the submitted request filename as evidence, and begins from a fresh
isolated runtime.
