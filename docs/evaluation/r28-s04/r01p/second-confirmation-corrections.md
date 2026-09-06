# Second confirmation — four bounded corrections

2026-09-06. R28-S04 / R01P remain in progress. No R02 or phase closure.

## Identity and authority

Starting local, unpushed HEAD: `39cc95581995195c9ef053c8bb889702a53456d3`; tracked tree was clean. Starting runtime aggregate matched `adeafbe3610f920f8da363763fe885d54345c314b9a67ba0af4f8754810ee398`. David approved these four corrections, clarified the framing/diagnostic approach, and authorised exactly d04/d24/d26/d29 once under the standing USD 5 / 90-minute envelope. The new correction remains **uncommitted** pending David's separate decision; the live regression is accurately described as frozen-uncommitted execution on the committed checkpoint.

Frozen runtime aggregate before and after dispatch: `b4dcff026fc21e72e06cfe36cf6a3f69d527513853ae1d63b1a411d2a0a4bac7` (935 files). Both mounted services were checked. No model, threshold, needs-schema, index or population change. Execution pin v8; generation prompt `grounded-generation-needs-v4` and adapter `openai-responses-needs-v2` identify the changed output rule; generation contract remains v2. Planner prompt/adapter remain v9/v4.

## Diagnoses and changes

1. **d04:** persisted answer started `Yes.` then correctly stated the manager is not informed. The opening and substance were generated within free-text answer parts, not an independently validated headline field. The needs-generation prompt now requests an evidence-supported direct statement; final needs-result validation rejects a detached opening Yes/No sentence or bare token. Connected wording such as `No, the guidance forbids ...` remains valid. This is a syntactic framing safeguard, **not** a semantic polarity detector or proof of arbitrary prose correctness. Invalid output is rejected, not silently rewritten; existing complete-result-only delivery prevents final answer persistence. No new retry mechanism.
2. **d26:** original record proves `schema / needs.0.fact_reference / value_error`, HTTP 200, completed output. The rejected value and exact original subcondition were not retained and remain unavailable. Fact-date validators now emit allowlisted precision, interval-shape, reversed-interval or calendar error codes, carried through the Laravel client and persisted usage. Only the bounded non-systemic fact-reference validation condition routes to a typed date/month clarification; it neither invents a date nor executes retrieval/generation. Historical generic `value_error` at that exact field is also safely handled. Other error paths remain failures. This mitigates the failure, not a claim that relative-date interpretation is solved.
3. **d24:** original plan lost `early` from its year-precision authority while retaining it in the source quote. On an ambiguous year resolution only, the resolver now checks the grounded quote for early/start/beginning/first-half or late/end/second-half/latter-half qualifiers. Conservative half-year intervals are rechecked against the same authority timeline; no qualifier, conflicting qualifiers, different year, or an authority change inside the half-year retains clarification. Exact dates/months are unaffected. No new authority kind or question-specific rule.
4. **d29:** original planner bound an internal family but placed the external phrase into historical authority. The resolver's zero recognised strategies previously shared the ambiguity branch with multiple conflicting strategies. Zero strategies now yield an empty match, allowing ordinary unavailable/comparison-incomplete handling; multiple genuine competing selectors still clarify. This is not an external-organisation alias catalogue or relaxed tenancy check.

## Provider-free verification

- Python: **216 passed**, covering needs/schema, generation needs request/output/transport, aspect coverage, generation contract and OpenAI adapter suites.
- Laravel: **206 passed / 816 assertions**, with three opt-in cross-service tests initially skipped in the main suite.
- Existing provider-free cross-service bridge: **3 passed / 37 assertions** separately, exercising real Python routes and Laravel orchestration with blocked external sockets. Its retained ungrounded-reference assertion was updated from the superseded hard failure to the already-established clarification outcome.
- Ruff lint and format passed for six files; scoped Pint passed; mypy passed on four production modules; `git diff --check` passed.
- Tests explicitly cover detached versus connected polarity wording, safe fact-date codes and no raw text, preserved failure receipts/no generation during clarification, ambiguous full-year and late-half authority, successful early-half authority, wrong-year/conflicting qualifiers, zero-match and competing historical selectors.
- Initial tests exposed omitted new diagnostic-code allowlisting and generation identity guards. Both were corrected before final verification and before any live call. No test failures were hidden as passes.

## One-pass known-case regression

Evidence directory: `/tmp/dolved-four-corrections.lSSctn/`. Original 30-question evidence remained unchanged (183 checksum entries verified). The four verbatim questions were selected from the verified questions-only file; no answer key was accessed. New four-question input SHA-256: `85bba7b58fb0186a4c77b4a54eca75ff669f304bf1267a29849873d274ed6711`.

| Case | Actual result | Interpretation |
| --- | --- | --- |
| d04 | Answered: EAP use is confidential; manager not told unless employee shares it. Document 14, chunks 69–71. | Correct direct framing observed; no detached polarity token. |
| d24 | Answered: early-2025 restrictor limit was 100 mm. Document 166, chunks 498–499. | Historical fact supported. This run planned `transition_period`, rather than the original period-resolution route; do not attribute the live answer solely to narrowing. |
| d26 | `invalid_fact_date_reference` clarification: “Which date or month do you mean for that event or personal record?” No citations/generation. | Safe mitigation exercised. New diagnostic `schema / needs.0.fact_reference / fact_date_precision` makes this recurrence more specific; no raw value retained or invented. |
| d29 | Qualified current-policy guidance. Document 63, chunks 283–284. Unsupported aspects explicitly state no numeric minimum and no evidence of adopting Thornfield's rule. | No historical clarification or external leak. Planner chose current retrieval, so the zero-match branch was not directly exercised live. The main displayed prose still leads with guidance, not the limitation: existing presentation limitation retained, not silently claimed fixed. |

All citations resolve within the primary workspace. Three generated responses and one clarification are **not a 4/4 independent correctness pass**. No full population run, selective rerun, mid-run change or new question authoring.

Usage: **14 physical requests, 0 retries; 78,839 input tokens; 5,000 output tokens; 81.552325 seconds; USD 0.02876789 configured-rate estimate** (not invoice). Within USD 5 / 5,400 seconds. Existing primary 982-chunk index reused; no re-embedding, AWS or judge call.

## Handover

`actual-results.md` contains full response text and safe diagnostics; `observations-and-citations.json` includes unsupported aspects and resolvable canonical citations; `run/` preserves individual cases, dispatches, usage and manifest. `code-freeze.json` binds runtime inventory; final file hashes are supplied separately. The original 30-case evidence and unrelated untracked files are preserved. Await David's commit boundary and fresh confirmation questions; do not rerun or begin R02 automatically.
