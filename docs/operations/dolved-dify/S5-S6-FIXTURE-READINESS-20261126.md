# S5–S6 isolated fixture readiness — 26 November 2026

## Outcome

Claude's released fixture package was verified before ingestion and preserved unchanged.
The private isolated Dify test arrangement is ready for Claude's final binding review.
No assessment question, retrieval probe, generation call, provider-validation request or
assessment submission was made. Production was not redeployed or changed.

The checksummed neutral package is retained outside the repository at:

`/Users/davidoliver/Desktop/dolved-experiments/dolved-dify-six-case-fixture-readiness-20261126`

Its `CHECKSUMS.sha256` SHA-256 is
`e0085c2eb15f86e5dca45c629e60cc400c2bb4525d5ef02776e6668ae4861d13`.
The earlier preparation package remains preserved.

## Released inputs

- `RELEASE.md`:
  `4ab8652b4820e97a6b537a5699eaec52759f031c020e1b57d34516ac45b6e473`
- `CHECKSUMS.sha256`:
  `0e456ef3cc8d7fa21dbfad64750499db1c690ed18d86fdbfe716888512c387b3`
- `visitor-parking-guidance.txt`: 867 bytes,
  `5f46c381846a36b44b6695767df78854874028d417c1ba216e64b162261451b4`
- `uniform-and-laundry-guidance.txt`: 882 bytes,
  `de99542bbf797c7fbe1e02cab44dcce3097f98f366b86206e5c5a84165dd3907`

The exact uploaded bytes were loaded back from Dify storage and matched the release.

## Isolated Dify identities

- Test app: `0c9885e5-5c77-4b1f-a7b8-6600c781fcb0`
- Unpublished draft workflow: `5bd7907b-9e1a-4851-9be5-bdc0271c2a2b`
- Draft graph SHA-256:
  `0a1821d11d14500415cb56ed4120e39b8ab08d1bff9c300156f3b0bff1299a16`
- Test dataset: `a4bb7857-5be3-4bd8-aa27-507221ef46bf`, permission `only_me`
- Test `scope_key` field: `13ce627d-46ef-49e2-a047-aad0a22e4812`
- Visitor-parking document: `0e871333-8fdd-498b-8d0b-bedd38aa9ccf`
- Uniform/laundry document: `a575ca98-7c18-4f29-b5a0-545a9b03c17b`

Both documents are completed, enabled and tagged `scope_key = global`. Each produced five
completed segments. `QUILLON-6620` and `MARLOW-3184` each survived wholly inside exactly
one stored segment. This proves stored ingestion only, not later retrieval exposure.

## Parity and intentional differences

The test workflow differs from production at exactly four graph paths: the test dataset
ID, the two test metadata-field IDs and the non-runtime editor `viewport`, which Dify's
supported copy operation omitted. Model, instructions, parameters, memory window, context
assembly, retrieval mode, Top K, weights, disabled thresholds/reranking and the filter
logic are otherwise identical. The filter remains:

`scope_key is global OR scope_key is {{trusted_site}}`

The test dataset exactly matches production's high-quality embedding, hybrid retrieval
and custom chunking settings: `text-embedding-3-large`, Top K 10, vector/keyword weights
0.7/0.3, blank-line separator, max 1000 and overlap 100. No tuning was performed.

## Production continuity and spend

Production still points to workflow v5
`5a85aa42-bca1-408f-953d-ecc08a130e6a`, graph SHA-256
`a53b32ad396f68c433e837cdaf73579f31334ae21bd0145720109f0fb0cc9561` and
prompt SHA-256 `0d9c2147a354bbd611166cac0b8bd7eda8132d8bf08bd1a2b23406921e597fae`.
The app-key hash is unchanged. Its corpus remains 11 eligible documents and 248
searchable segments at the frozen binding
`733a20a23390f9e1a02116922aa9613c3afd3e128a5645c9eba4710140ccba71`;
neither fixture occurs in production.

Fixture embedding recorded 362 Dify document tokens. No provider receipt is available,
so actual billing is unknown rather than zero. A conservative USD 0.01 preparation reserve
leaves USD 9.99 of the combined allowance for later separately authorised execution.

The previously reported Dify memory-limit incident remains in the operational record. No
resource limit or production service was changed here. Claude's final review and David's
separate execution release are the next actions.
