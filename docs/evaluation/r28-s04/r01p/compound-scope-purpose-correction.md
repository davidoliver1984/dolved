# R01P compound scope-purpose correction — single-pass report

2026-09-08. **Not ready to commit: i01 failed the requested acceptance criterion.** No commit or push. R28-S04/R01P remain in progress; ADR-0039 Proposed.

## Verified starting state and actual diagnosis

HEAD remains `9210c744be40b2517adf6c97e78de7c21bcdf7a9`. The expected uncommitted scope implementation and local-receipt follow-through were present. Runtime aggregate verified `a698bf058298fc68496b8f4f4c0a11a6dfab0205afd82eb7ee3367b1fce17486`; no unexpected drift.

The prior i01 and i24 planner request bodies were reconstructed through a read-only catalogue query using their recorded request UUIDs, timestamps and verbatim turns. **Both reconstructed body digests exactly matched the immutable dispatch records**: i01 `6be98bd7d38312693756da671aa2d548ae5adf9be3ab99f5b85c38b5828c4789`; i24 `789d94a02cc9d0006d1ae19b179e2288f1bdc7686c82fd8f7ce3c7ed050709fb`. See `prior-inputs.json` and `reconstruct.php` in the evidence root.

The exact prior planner source was reconstructed and its file hash checked against the previous freeze. `prompt-comparison.json` retains the actual system instruction and both plans. Catalogue and output schema were the same; both requests had one user turn, and the evaluated-at timestamps differed by 27 seconds. The semantic input difference was the question wording: i01's standalone register-check question versus i24's shorter check wording plus unrelated booking clause. No separate compound classifier, decomposition request or deterministic override exists: one structured model response returns aspect, operation and binding together.

Observed output: i01 rewrote its aspect as whether the Asbestos Management Procedure requires the check and emitted scope_enquiry; i24 n1 instead rewrote it as “Retrieve any guidance…” and emitted retrieve. This demonstrates purpose drift in the joint decomposition output. It does **not** expose model internal reasoning or prove that compound framing alone (rather than the other wording changes or model variance) caused it. There is no evidence of a hidden compound heuristic. The testable instruction gap was absence of an explicit per-original-clause purpose-preservation rule; the correction targets that gap, not a claimed proven attention/order mechanism.

## Bounded change

The planner now chooses each purpose from the original clause before aspect rewriting, preserves it when an unrelated clause is added, and must not treat its own generated “Retrieve any guidance…” wording as evidence for retrieve. A generic equipment-policy/site plus booking example illustrates separate purposes. Working instructions remain retrieve, version changes remain compare, and no scope default, post-hoc reclassification or eligibility widening was added.

Prompt identity advanced to `bounded-evidence-needs-v14`; pinned execution identity to `bounded-needs-slice-v14`. Contract remains evidence-needs-plan-v3; adapter remains structured-chat-needs-v6. Matching Laravel identity checks/tests and v3 README were updated. No model, generation prompt/contract, applicability resolver, operational thresholds, index, retry rule or financial ceiling changed during this follow-up.

Preflight caught remaining v13 pins before any provider call; they were aligned and tests rerun. `code-freeze.json` is retained as an unused preflight-only draft; **`code-freeze-final.json` is the actual run freeze**. Final runtime aggregate `65eb3a1c467bb7f059fb2b6e1842e2f0eced010edcc27e30236e6c2ae1116d30` was verified unchanged after the run.

## Provider-free proof

**174 Python tests passed; 214 Laravel tests / 1,025 assertions passed.** Three optional cross-service bridge tests were skipped, not claimed executed. Ruff, format checks, Pint, scoped mypy, shared schema fixtures and git diff --check passed.

New adapter/schema fixtures cover standalone scope, scope+operational, operational+scope, two operational needs and comparison+operational. They exercise actual structured response validation and prove that operations survive adaptation unchanged and no post-hoc conversion occurs. They also verify the instruction is present. Their model outputs are recording fixtures: **they do not prove live model judgement or classification accuracy**. The live pass below is the semantic check. Existing provider-free scope-only conversation/accounting publication, adversarial safety rendering, mixed evidence separation and ordinary temporal/operational tests remain passing.

## Exactly one live pass

Evidence: `/private/tmp/dolved-compound-scope.Z7U0qD`. Same verbatim three questions; no prior answers supplied; same isolated primary workspace, 982 chunks and active corpus. No re-embedding, code changes during execution, reruns or new questions.

### i01

Question: Does Harbour View need an asbestos register check before drilling into a wall there?

Execution: clarification_required; retrieval: clarification_required; generation: not reached.

Purposes: n1=scope_enquiry. Bindings: n1=topic [].

Published response:

Which document or policy do you mean?

**FAIL against requested criterion.** The planner selected scope_enquiry but paired it with topic / empty family_ids. The resolver's explicit single-family requirement therefore emitted unresolved / ambiguous_document_subject and returned clarification. No snapshot was guessed, no scope fact published and no generation provider call occurred. This is a purpose/binding compatibility gap, not the previous accounting failure and not a ranking failure.

No validation or native retry fired. Case time 8.648621 seconds; estimate USD 0.00536600. Full planner need/authority/applicability fields, receipt identities and citations are in the case JSON/export.

### i16

Question: Does the asbestos register procedure cover Oakfield Lodge?

Execution: completed; retrieval: evidence_found; generation: answered.

Purposes: n1=scope_enquiry. Bindings: n1=matched [c6d8a80f-3076-4950-ba36-1ccafb0943b4].

Published response:

The recorded scope of "Asbestos Management Procedure" includes "Oakfield Lodge". This is a statement of recorded policy applicability only; it does not establish what action is safe or required. The reason for this scope is not established by the scope record.

**PASS for this bounded case.** Correct direct-match applicability snapshot citation (document 226) and explicitly limited coverage statement published. No generation provider call; zero-cost local receipt persisted successfully. The prior publication defect is exercised successfully here.

No validation or native retry fired. Case time 7.934209 seconds; estimate USD 0.00540075. Full planner need/authority/applicability fields, receipt identities and citations are in the case JSON/export.

### i24

Question: Quick one — does Harbour View need an asbestos check before drilling, and can agency workers still get booked with just a phone call?

Execution: completed; retrieval: evidence_found; generation: answered.

Purposes: n1=scope_enquiry, n2=retrieve. Bindings: n1=matched [c6d8a80f-3076-4950-ba36-1ccafb0943b4]; n2=matched [e7072956-8201-4db8-81a6-fa4ae23b0c22].

Published response:

Recorded policy scope

The recorded scope of "Asbestos Management Procedure" does not include "Harbour View". This is a statement of recorded policy applicability only; it does not establish what action is safe or required. The reason for this scope is not established by the scope record.

Other requested guidance

Bookings must be made through one of the two approved framework agencies and confirmed in writing by email before the shift; the previous allowance for verbal telephone confirmation has been removed and a written booking confirmation is now required in every case. Before confirming the shift, the registered manager or shift lead must confirm the agency worker's DBS check and mandatory training status with the agency. Where a genuinely urgent gap arises and the registered manager seeks to contact a wider agency pool, the Alderbridge Quality Lead's direct approval is required and the same written-confirmation, DBS and training verification standards still apply. On arrival, reception or the shift lead checks the agency worker's photographic identification against the agency's booking confirmation, delivers the shortened site induction, and agency workers must not administer controlled drugs unless they have completed Alderbridge's controlled drugs competency check.

**PASS for this bounded case.** n1 scope_enquiry resolves the Asbestos document 226 snapshot outside Harbour View; n2 remains operational. Published sections are separate, with one applicability citation and ordinary citations to document 187 / chunks 588, 589, 590 and 591 (all primary-workspace identifiers resolve). No excluded asbestos chunk was supplied for generation. The agency answer correctly requires written confirmation rather than phone-only booking; no inference that drilling is safe appears.

No validation or native retry fired. Case time 21.161416 seconds; estimate USD 0.00831871. Full planner need/authority/applicability fields, receipt identities and citations are in the case JSON/export.

## Remaining finding and commit boundary

The new compound purpose instruction worked in i24 this time, but i01's purpose and binding did not agree: scope_enquiry requires an identified authorised family, while topic supplies none. The existing prompt also tells unnamed ordinary guidance requests to remain topical. The observed result exposes the unresolved boundary between an implicit policy/site coverage question and named-document binding; it does not justify silently selecting a family, making every topical question a scope enquiry, or loosening catalogue enforcement.

No further prompt tuning, implementation or live attempts were made after these results. **Commit withheld exactly as instructed.** The next bounded decision is how to reconcile scope-purpose selection with legitimate semantic family identification when a user has not supplied the literal policy name. Preserve clarification for genuinely ambiguous identities and the prohibition on operational widening. This report does not claim that another prompt change is already proven to solve it. No general audit, R02 or new question authoring.

## Usage, identity and preservation

Six provider requests: three OpenAI planner, one Voyage query embedding, one Voyage reranker, one OpenAI generation. Zero retries. **56,207 input / 2,920 output tokens; USD 0.01908546 estimated; 37.746971 seconds**, within USD5/90minutes. Local scope rendering is not a provider call. Costs remain configured-rate/adapter estimates, not an invoice.

All 37 previously inventoried unrelated untracked files remain unchanged and untracked; previous scope evidence checksums verify. The v8 question file was not opened. No AWS, push, commit, answer-key access, index/model changes or extra live questions. Tracked implementation remains uncommitted, nothing staged. Full final changed-file SHA-256 inventory and evidence checksums accompany this report.
