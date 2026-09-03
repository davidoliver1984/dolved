# R28-S01 authoring output contract v3 compatibility amendment

Status: **APPROVED BY DAVID ON 2026-09-03 FOR FINAL 74-CASE POPULATION
SERIALIZATION — R28-S01 remains in progress; the population itself is not yet
finally audited or frozen, and R28-S02 remains blocked.**

Version 3 is a compatibility-only amendment to the independently authored
population output contract. It does not alter case content, population counts,
coverage requirements, closed vocabularies, evidence lineage, access controls
or evaluation policy.

The active identities are:

- schema version: `r28-independent-authoring-output-v3`;
- contract identity: `dolved-v4-independent-authoring-output-v3`;
- aggregate SHA-256:
  `58e4d4b3ebbde74118bbbd287240ef861fea9035aa291642e2be2a97c6ae1624`.

The aggregate is reconstructed from the six ordered governed files listed in
`authoring-output-contract.md` by hashing each UTF-8 repository-relative path,
one NUL byte, the exact file bytes and one NUL byte.

The coverage contract remains independently versioned as
`r28-authoring-coverage-contract-v2`. It still requires exactly 74 semantic
cases, two variants per case, 148 utterances, scopes 62 primary / 6
foreign-tenant / 6 security-test, and the same 36 minimum coverage values.

The only compatibility changes are:

1. `requester_role` retains its string type and minimum length of one, while
   its maximum increases from 120 to 200 characters.
2. Each exact expected-evidence `quotation` retains its string type and minimum
   length of one, while its maximum increases from 500 to 2,000 characters.
   It remains a bounded exact-source excerpt, not a document payload.
3. The question `utterance` maximum remains 500 characters.
4. `as_of_date` follows this explicit matrix:
   - `CURRENT`: `null` only;
   - `VALID_AT_DATE`: a valid non-null ISO date only;
   - `COMPARE`: `null` only;
   - `HISTORICAL_REFERENCE`: `null` or a valid ISO date;
   - `CLARIFICATION_REQUIRED`: `null` or a valid ISO date.

Every non-null date must parse as a real ISO calendar date. The JSON Schema and
executable validator enforce the same matrix.

Versions 1 and 2 remain historical, superseded identities. Their aggregate
SHA-256 values are respectively
`c7e4f6bce57be48e69bb6f3c57e6cb34f5130859efd782e9ad5db7503a163e3c` and
`57ebb52ae6814f4912583c90ec399c60a65e82dc872cfdb21afe10f57871df68`.
Legacy identities and every mixed schema/contract identity pair fail closed.

No authored population, corpus material, question, quotation, protected
evaluation material or provider output was used to prepare this amendment.
