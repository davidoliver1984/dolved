# r08/r17 planner binding-variance diagnostic

Ten explicitly authorised OpenAI planner-only calls ran once each through `RetrievalClient::planNeeds`, with the primary workspace alone opted into evidence needs. There was no eligibility, search, reranking, answer generation or publication. The first five used r08's exact standalone question. The second five used r17's exact **full compound turn**, preserving the context in which n1 previously failed; n1's original clause is retained in every plan's `question_span`. The evaluated-at value was fixed across all ten calls. No code, prompt, alias or configuration changed during sampling. The frozen runtime-source aggregate and HEAD were unchanged before and after.

| Call | n1 operation | n1 subject_status | Family IDs | n1 applicability |
| --- | --- | --- | --- | --- |
| r08-01 | scope_enquiry | topic | none | generic |
| r08-02 | scope_enquiry | matched | Key Holder Procedure (`5a84914a-79a1-4e55-9e83-1ac42dda6720`) | named North West |
| r08-03 | scope_enquiry | matched | Key Holder Procedure | generic |
| r08-04 | scope_enquiry | topic | none | named North West |
| r08-05 | scope_enquiry | topic | none | named North West |
| r17-01 | scope_enquiry | topic | none | named North West |
| r17-02 | scope_enquiry | topic | none | named North West |
| r17-03 | scope_enquiry | topic | none | named North West |
| r17-04 | scope_enquiry | matched | Key Holder Procedure | named North West |
| r17-05 | scope_enquiry | topic | none | named North West |

r08 matched the family in 2/5 samples; r17 n1 matched in 1/5. In both sets, only 1/5 combined a matched family with named North West applicability—the shape required for a location-resolved scope fact. All ten retained `scope_enquiry`; all five r17 samples also retained a separate second need. The full plans, bindings, aspects, exact quotes, lineage and usage are in `run/calls/*.json`.

The earlier v17 round-15 r08 record was topical, while r17 was matched with named North West. The later v18 17-case regression recorded both as topical, with r17's applicability generic. The current ten v18 samples demonstrate **non-deterministic planner judgment for both phrasings**, so the single failed r17 regression is consistent with a binding-judgment miss rather than a deterministic inability to bind that phrase. They do **not** establish that v18 has the same binding rate as v17, or rule out an effect from the instruction change: only one earlier v17 sample per question exists in this comparison. The new instruction is in the applicability-quote portion of the same whole prompt, close to but not inside the later family-binding guidance. A language-model planner does not have a structural boundary preventing one instruction from influencing another decision. No causal claim is made.

Usage: 10 planner provider requests/attempts, zero retries, 173,430 input tokens and 9,479 output tokens; elapsed 96 seconds. Planner cost fields were unavailable, so no complete USD figure can be reported. No retrieval, Voyage, generation or AWS call occurred. No fix, commit or push occurred. This diagnosis does not retroactively satisfy the previous correction's predeclared clean live gate.
