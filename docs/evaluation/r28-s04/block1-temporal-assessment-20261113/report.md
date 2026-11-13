# Temporal assessment: provider-free preparation — 2026-11-13

Block 1 remains HOLD. Main unchanged. This report will be supplemented by actual
probe results; no provider success is implied by fixture validation.

Reviewer selection is unchanged (ccb35dd, SHA-256
530c05066fc7f9db6dd6fa45fef831fa9720a3463efe056f280500d6fad7b207).
Amendment 1 is unchanged (8fc2125, SHA-256
54b9d9bf7c5a9ffec5dfd934aef1605ed3a234e74f138a636ef8190a1f5b934f).
T11's immediate-risk exception and July2024 history floor match both ingested
Missing Person versions. T12's new current/June2025 conflict is observable from
its question; Staff Benefits v1/v2 in the frozen ingested snapshot support
5–10%/5–15%, effective April2025/April2026. The prior source-verification package
and its checksums are unchanged. The amendment resolves its recorded stop.

## Implementation and verification

Mandatory temporal assessment inside the existing checker, receipt v3, exact
need coverage/quote grounding/global consistency checks, bounded PHP diagnostic
retention. No authority rewrite, timeline change, extra call or attempt; 4096
completion tokens unchanged. Citation validity is not semantic correctness.
Existing unverifiable-flag handling is preserved.

Focused: 46 Python tests; 7 Laravel tests (144 assertions). Full suites: 38 AI
failures, 1325 passed, 4 skipped; 2 Laravel failures, 865 tests, 9 skipped.
`suite-comparison.json` lists every exact failure identity and JUnit SHA: no new
or resolved failures against the retained migrated baseline. Old test fakes gain
the mandatory response field; no existing assertion changed. Initial AI wrong
mount-depth collection errors and Laravel 128MB process exhaustion are retained
separately, not counted as completed suites. Final AI used `/workspace/apps/ai`;
final PHP used a 512MB test-process limit. Focused Ruff/Pint also checked.

## Frozen mechanism experiment

`probes.json`: 25 plans; `expectations.json`: separate transformations, reasoning,
source expectations and denominators. All plans pass Python schema, grounding and
authorised-catalogue checks (retained development plan keeps its original topic
binding). All 25 pass PHP validation; `php-plan-validation.json` shows actual
execution mapping. No provider call was used to construct these plans.

T01–T03 historical rule dates become fact-only/current in the wrong plans.
T04–T07 wrongly promote old event context into historical rule authority.
T08 retains the event date but wrongly loses that same date's authority role.
T09 consistently swaps the leaving/request roles; leaving predates all versions.
T10 loses the historical execution lane, even though current text might mention
the old number. T11's correct compare plan supports the reviewer's explicitly
qualified two-rule alternative, not a claim that either rule applied to the
undated event. Its wrong plan silently chooses current. T12's correct compare
executes historical and current; its wrong plan silently keeps current. The
retained input16 accepted plan is an additional labelled wrong development probe,
not reconstructed or counted as fresh confirmation.

Five calls per plan: 60 correct, 65 wrong, 125 total. Pass requires at least 59
semantically appropriate citation-verified wrong rejections; at most 3 false
correct-plan rejections; no completely missed temporal pattern; complete valid
execution. Manual assessment must check the cited defect, not merely count a
verified citation. Failure stops without tuning or live confirmation.

Budget cap was explicitly removed by David. `run_probes.py prepare` computes and
records the full 4096-output-token-per-call bound plus conservative UTF8-byte input
and framing bounds at retained rates, before execution. One-use exclusive consumed
record, exact pushed hold SHA, runtime/input hashes and per-call ledger apply.
Only question turns and plan enter the provider; expectations are never sent.
