# Document deletion worker HTTP contract — version 1

These six schemas describe the existing request and response envelopes for
the three purpose-scoped deletion operations:

- `document.deletion.claim`;
- `document.deletion.complete`;
- `document.deletion.fail`.

`worker-operation-vectors.json` is the authoritative shared fixture source
used by both PHPUnit and Pytest. It contains canonical valid request/response
pairs and common negative mutations for unsupported versions, additional
properties, missing required identity and invalid outcome enums.

The claim request is the unchanged `document.deletion.requested` v1 event.
The callback requests use `contract_version: 1`. No older deletion-worker HTTP
version remains supported, and an unknown version is rejected rather than
adapted.
