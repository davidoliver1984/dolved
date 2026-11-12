# Interpretation check v2 — verifiable citations, semantic judgment separate

The existing one bounded checker call returns closed `code` and nullable `citation`.
The citation fields are `user_turn_id`, `user_quote`, `need_id`, `plan_field`,
`plan_quote`, `inspected_need_ids`. Quotes are at most 1000 characters, turn IDs100,
need IDs10, inspected IDs at most three. No free-form reasons or replacement plan.

Application receipts preserve `checker_code` independently of the effective `code`,
plus `citation`, `citation_verification` (`verified`, `unverifiable`, `not_applicable`),
closed `verification_reason`, the reviewed plan and usage. **Verified refers only
to citation grounding, never to the semantic verdict.** Laravel bounds/preserves
these fields and continues reading historical v1 receipts.

- scope_added: exact phrase in an identified need's executable field, absent from
  every authorised turn. Merely citing application-computed periods does not qualify.
- meaning_changed: exact user phrase/turn and exact plan phrase/need/field exist.
  Their semantic incompatibility remains the checker's judgement; prior turns may
  explain the difference. Computed relative periods alone cannot support the flag.
- clause_omitted: user clause exists verbatim in the specified turn; inspected IDs
  identify all existing needs exactly once. No lexical absence requirement: copied
  question_span is provenance, not coverage; executable paraphrases and lossless
  merges are possible. Whether the clause is omitted remains semantic judgement.

Closed verification reasons: missing_citation, user_quote_not_found,
inspected_needs_mismatch, unknown_need, plan_quote_not_found,
plan_quote_in_user_turns, application_grounded_period. Unverifiable flags are logged
but effective code is accepted, preserving the validated plan without another
attempt. Verified flags retain existing closed-code retry/terminal-failure handling.
Transport/malformed responses retain existing unavailable handling. No extra call,
no retry increase: at most two planner attempts total. No citation text is feedback
to the second planner attempt, only existing stage/field/code.

No catalogue or evidence is added to the checker prompt. evaluated_at is supplied
only to local deterministic citation verification. A citation-valid false flag
still fails the zero-false-flags checkpoint criterion. Every unverifiable flag's
plan must be manually reviewed for missed semantic defects in checkpoint evidence.
