# R28-S03 V4 corpus materialisation

`R28-S03-V4-CORPUS-MATERIALISATION-0002` materialises the frozen V4 corpus
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
