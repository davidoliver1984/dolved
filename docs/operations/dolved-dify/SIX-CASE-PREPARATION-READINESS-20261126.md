# Six-case supplementary assessment preparation readiness

**Observed:** 26 November 2026, 09:08 GMT  
**Status:** production correction deployed and verified; isolated fixture arrangement
awaits Claude's explicit fixture release; no assessment case executed

## Stop boundary observed

No fresh assessment question, expectation or sealed reviewer material was accessed. No
retrieval, reranking, generation or other provider-validation probe was submitted. The
fixed order remains S1 F20, S2 insufficient evidence, S3 ordinary answerable, S4
insufficient evidence and S5-S6 isolated injection.

## Delivered production correction

- Branch: `codex/pilot-unified-design-20261125`
- Focused implementation commit: `7b4c1df3b4f1437010601774021f1f2175d766be`
- API/worker/scheduler image:
  `sha256:bff6314f3f77d905b4f6568b9a2294dffc25c889e66b2736a39d62db4465bfa4`
- Web image:
  `sha256:1e5ad967280629b06df3106712b6f943e2b41d9068c88ce2adddb2e32c42b6ee`
- Every application container carries the full implementation commit as its OCI revision
  and has a clean non-OOM runtime state.
- Dify application: `32199630-6a11-40a0-96ca-618560e6e394`
- Previous workflow: `fc614ecd-9cdf-446e-96bf-d193f1a2afde`, version `4`, canonical graph
  SHA-256 `77948ad3d5056f701c386b46ea088f008897e0fae49257baa84c913fa53de009`
- Corrected workflow: `5a85aa42-bca1-408f-953d-ecc08a130e6a`, version `5`, canonical graph
  SHA-256 `a53b32ad396f68c433e837cdaf73579f31334ae21bd0145720109f0fb0cc9561`
- Published prompt SHA-256:
  `0d9c2147a354bbd611166cac0b8bd7eda8132d8bf08bd1a2b23406921e597fae`

A lossless read-back of versions 4 and 5 found exactly one changed path:
`$.nodes[1].data.prompt_template[0].text`. Model `gpt-5.6-sol`, provider, reasoning
`medium`, maximum output `4096`, service tier `default`, memory enabled/window `10`,
context selector, trusted-site insertion, retrieval nodes, dataset reference, Top K,
thresholds and current-question insertion are unchanged. The production Dify app-key
binding has the same non-reversible hash before and after deployment.

The built web bundle contains the exact label `Sources retrieved` and help text
`Documents provided to help generate this answer. Check the relevant source before
acting.` Display and copy cleanup remains limited to observed U+E200 cite/filecite
wrappers and exact `【Retrieved context】` markers.

## Verification and corpus binding

- Structural prompt/configuration verifier: passed.
- ChatWorkspace: 24 tests passed.
- Focused ESLint and diff checks: passed.
- Immutable production image build: passed.
- Static production preflight: passed.
- Full provider-free running verification: passed without retrieval or generation.
- Production corpus: exactly 11 eligible documents and 248 searchable segments.
- Segment snapshot digest:
  `733a20a23390f9e1a02116922aa9613c3afd3e128a5645c9eba4710140ccba71`,
  identical to the frozen readiness binding.
- Retained Dify history remains 18 documents and 288 segments. Dolved has no queued or
  reserved jobs and no ingestion retries. One historical failed-job row remains; the
  previously frozen check concerned failures since corpus restoration, not all-time
  history.

During supported workflow publication, the temporary Dify service process briefly caused
one API Gunicorn worker to be OOM-killed inside its 1 GiB container. Dify replaced the
worker automatically. The API container was then cleanly restarted, returned healthy at
about half its limit with `OOMKilled=false`, and the complete production gate passed. No
stored state or configuration was lost or changed by the restart.

## Rollback

Restricted backup:
`/srv/dolved-pilot/backups/20261126-bounded-improvement-predeploy`

Its checksum manifest SHA-256 is
`6c78a4e9616234556cf66e01709a13694f6c1f84419794cf334121fc6fd465b0`.
It contains both database dumps, workflow version 4, the protected environment,
production compose file and prior identities. The prior application revision is
`8b43ded8964f5ff52ab49d9c3159382f2af36d5d`; its API image is
`sha256:06dfe41dce7cebc5377ba8e0e2ae55e69e69d54bda5d2e922931221f2c7022e0`
and web image is
`sha256:c175a3e91fd9fb4076228c602085b7a773cfb861a456769e5cb60876d83e25b5`.
Application rollback uses those immutable images; workflow rollback selects/restores
version 4 through supported Dify controls. Sensitive exports remain restricted on the VPS
and are not included in Git or the neutral handoff.

## Fixture and isolated-app prerequisite

Claude's neutral `HANDOFF.md` and `CHECKSUMS.sha256` are present with the authorised
SHA-256 identities, but the two fixture files and a release receipt are not present at the
neutral handoff level. They remain sealed. Therefore no fixture was opened, copied,
substituted, embedded or tuned against, and no incomplete test app/dataset was created.

The exact prerequisite is Claude's explicit release receipt plus these two setup files at
a neutral location, without requiring access to the sealed questions or reviewer
directory:

- `visitor-parking-guidance.txt` SHA-256
  `5f46c381846a36b44b6695767df78854874028d417c1ba216e64b162261451b4`
- `uniform-and-laundry-guidance.txt` SHA-256
  `de99542bbf797c7fbe1e02cab44dcce3097f98f366b86206e5c5a84165dd3907`

After that release, the remaining preparation is to create the private copied app and
dedicated dataset, ingest only those two global fixtures, bind the copied filter to that
dataset's own location metadata, and read back identities, chunks, segment counts and the
production-versus-test configuration diff. No retrieval rehearsal is permitted.

## Spend accounting

No provider operation was deliberately initiated during this preparation, so there is no
preparation provider receipt. Fixture embedding has not happened. Unknown billing is not
reported as zero and provider billing was not queried; nominally the authorised USD 10
allowance remains available for fixture ingestion and the later one-use execution, but it
is not a provider-enforced hard cap.

## Later evidence capture

For S1-S4 retain the Dolved conversation, question-audit and Dify native conversation,
message, workflow-run and node-execution IDs. Preserve separately the raw stored answer,
rendered answer, copied answer, supplied retrieval context, source references/links and
actual LLM runtime messages. Record whether either of the two explicitly permitted marker
classes was removed at display/copy time.

For S5-S6 retain the isolated Dify app/workflow/dataset/document IDs, retrieval node
output, exact fixture marker in `process_data.prompts`, LLM runtime messages and source
references. These direct-Dify cases do not establish Dolved display handling, site
handling or production-corpus retrieval coverage. If a fixture marker is absent from the
captured generation input, mark that case unexercised and do not retry it.

Preparation is not an assessment pass. The next safe action is Claude's narrow
readiness/binding review and fixture-only release (or David's later separately authorised
execution release if Claude keeps fixtures coupled to the questions).
