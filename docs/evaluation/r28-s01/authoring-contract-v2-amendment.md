# R28-S01 authoring contract v2 amendment

Status: **APPROVED BY DAVID ON 2026-09-03 FOR FINAL POPULATION AUTHORING —
R28-S01 remains in progress and is not finally frozen.**

The independent, read-only, source-backed feasibility audit recorded verdict
`R28_V4_AUTHORING_CONTRACT_REQUIRES_NON_WEAKENING_EXTENSION`, starting
authoring checkpoint
`1fcb0acb3f40c7fdec52da69cf768fb347d255f501dbaac0c0cb1dcc5a0b8c8a`, and
restricted-view identity
`8f73c9c12a843be9641698f39db60243a977e6c1c700a3f89f72dbbb890e44b9`.

Version 1 required 72 semantic cases, two variants per case, 144 utterances and
exact scopes 60 primary / 6 foreign-tenant / 6 security-test. It was not used
for a final accepted population. Five independently audited batches produced
60 accepted cases. The final feasibility audit established that the frozen
72-case structure could not truthfully satisfy every existing coverage minimum
from the available source evidence, and that two additional primary cases were
the minimum non-weakening extension.

Version 2 therefore requires 74 semantic cases, two variants per case, 148
utterances and exact scopes 62 primary / 6 foreign-tenant / 6 security-test.
All 39 coverage requirements (the three exact scope counts plus the 36 entries
in `minimum_case_counts`) retain their v1 keys and values except for the approved
primary exact-scope extension from 60 to 62; every one of the 36 minimum values,
all thresholds, safety requirements, schema shape, closed vocabulary and
evidence rules remain unchanged.

The two added primary cases complete inherited-applicability coverage through
distinct legitimate needs governed by (1) Midlands-wide Key Safe Procedure v2
at Oakfield Lodge and (2) North-West-only Key Holder Procedure v1 at Riverside
House or Moorland View. No authored question, quotation or expected answer is
specified here.

Version 2 supersedes v1 for final serialization. The 60 already accepted
semantic cases remain valid because nothing governing their shape, vocabulary,
minimum coverage or evidence was weakened. The final population must be
validated entirely under v2. Outputs or identities from v1 and v2 must never be
mixed or presented as one identity.

The superseded v1 aggregate is
`c7e4f6bce57be48e69bb6f3c57e6cb34f5130859efd782e9ad5db7503a163e3c`.
The v2 aggregate is
`57ebb52ae6814f4912583c90ec399c60a65e82dc872cfdb21afe10f57871df68`.

R28-S01 remains in progress, and R28-S02 remains blocked on R28-S01 acceptance.
