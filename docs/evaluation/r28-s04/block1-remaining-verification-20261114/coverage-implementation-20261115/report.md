# Bounded coverage implementation — 15 November 2026

Hold branch only. No provider calls, deployment, decision run, main merge or
Block 1 closure. Starting commit `73655047625d17d5b2ca5c62c67682919bb3d631`.
David withdrew time-cap accounting; no allowance ledger is maintained.

## Implementation and limits

One optional signed coverage-selection call receives original question context,
grounded requested spans and independently qualified candidate associations.
Bounds remain six aspects, twenty physical passages, five final physical passages,
30,000 input tokens, 4,096 completion tokens and sixty seconds within the remaining
deadline. No retry, search, reranking or threshold change. Decomposition/support
are model judgements; exact IDs/quotes establish provenance only.

The deterministic allocator selects complete proposed support units and records
unmet/capacity aspects; ties retain existing ranks and stable IDs. Spare slots
retain ordinary authorised evidence, so a model's `no_support` cannot itself
suppress a legitimate answer. Generation still decides support under its checks.

Each receiving need is independently re-resolved and canonical hydration checked
before/after the call, including application-selected temporal targets. A physical
passage may now have multiple receiving associations, supplied once under needs
generation v6. Assembly, Python/PHP temporal validation, ownership and persistence
use the receiving association, never another need's authority. Existing receipts
remain readable. A newly shared association is not labelled as new retrieval.

Typed timeout/transport/unavailability retains ordinary allocation and records the
limitation/attempt. Invalid or incomplete output is not consumed: controlled item
failure. Authentication, identity/authority/corpus integrity, budget/deadline and
unknown failures remain fail-closed/run-stop conditions. No budget or retry reset.
The existing diagnostic exporter retains proposal, allocation, reason and usage.

## Review ceiling decision and reservation

David approved a measured bounded increase. The existing source-review call now
has 32,768 output tokens rather than 1,024. A realistic large receipt containing
fifteen source associations (three needs × five passages), with 8,000-character
source quotations, measures **24,634 o200k tokens**. Adding 4,096 reasoning tokens
fits the new bound. This is local sizing, not measured provider reasoning or a
guarantee for every pathological schema-valid response. Incomplete responses,
even if partly parsed, remain rejected and their usage retained. No extra review
call/retry. `receipt-sizing.json` contains the reproducible accounting.

Decision-run reservation must add 31,744 output tokens per review, up to 63,488
across two generation attempts, plus the selector's 30,000 input / 4,096 output
bound. Dollar pricing remains unavailable here; no provider expenditure occurred.
Do not reuse the old decision-run reservation unchanged.

## Provider-free verification

`suite-comparison.json` compares exact identities with the retained prior raw-JUnit
baseline, not just counts. Initial failed integration runs are retained separately;
`ai-complete.xml` / `api-accounting-complete.xml` are the final gate. No assertion was weakened.

Final AI: 1,509 tests, 1,467 passed, 38 failed, four skipped. Final Laravel: 949
tests, two failures, nine skipped, 5,743 assertions. Exact failure sets are
unchanged: zero new and zero absent. No genuinely resolved baseline failure is
claimed; the sometimes non-reproducing inode-substitution test reproduced here.

- Real retrieval/client integration: two ordinary searches, one controlled selector
  call, one physical passage independently authorised for two needs, including a
  need with no retrieved candidate. No extra search; no semantic-proof claim.
- Real allocation/assembly/validation/persistence/reload: physical sharing retained;
  removal of receiving ownership rejects the answer. Historical readers unchanged.
- Omitted needs, invented IDs/quotes, saturation, shared support, explicit unmet
  coverage and legitimate-answer negatives; invalid proposals never consumed.
- Receiving-family restriction, deleted-document rejection, version/owner mismatch,
  smuggled source text and legacy-contract sharing rejection.
- Signed workspace identity/replay checks, pre-dispatch input capacity, expired
  deadline, typed optional/unavailable versus invalid/authentication/budget failures.
- Large source receipt and truncated/partially parsed receipt accounting.

Python source typecheck passes for 169 files using container-local cache. The
mounted mypy SQLite cache produced a disk-I/O error; changing cache location, not
source, resolved it. Repository-wide Ruff reports three pre-existing import-order
failures in untouched tests; no unrelated cleanup was performed.
Pint's production-wide check reports one existing formatting issue in untouched
`LocationReferenceCandidates.php`; changed PHP files have no reported issue.
Deadline expiration during the optional call retains its attempted-call receipt
and remains a run-stop condition, never a fallback permission.

## Completion matrix (implementation is not live acceptance)

| Obligation | Position |
|---|---|
| Item 28 allocation loss | Mechanism implemented/provider-free; live effect unmeasured |
| Item 26 encryption allocation loss | Same bounded allocation mechanism; live effect unmeasured |
| Item 26 absent n3 cyber-route candidate | Independent receiving association implemented/tested; not claimed repaired by allocation alone |
| Item 24 source/requirement substitution | Prior source-anchored review retained; richer receipt now sized; semantic judgement remains model-dependent |
| Item 29 shared paragraph temporal use | Prior per-(part, need) mechanism retained, v6 receiving authority tested; no new live proof |
| Item 19 historical versus transition interpretation | **Open**; month precision repair does not settle intent |
| G3 temporal grounding | **Open**; improved feedback remains live-unverified |
| Item 17 family-review no_match | Separate retained finding; no inferred cause or repair |

Frozen questions, expectations and previous verdicts unchanged. Package remains
HOLD; no assertion that all obligations are complete or that the decision run is
ready. This publication ends before live execution.
