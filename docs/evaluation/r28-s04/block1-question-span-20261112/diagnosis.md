# Grounded question-span repair — diagnosis and frozen acceptance

Baseline `0f931126ec58bffa8f15d32540ad2395da57953c`; no provider calls for diagnosis.
Source: `../r06-governed-evidence/R28-R07-BLOCK1-CLOSING-20261112-01/cases/01-v4.case.corrected-b06-06-v1.json`.

1. **Confirmed:** n3's reranked rank 1 is `afd325b1-df26-51f5-bbc2-fe2e69b23c1c`,
   score 0.567314624786377, and it appears in needs_final_evidence and generation_input.
   Frozen `../r07-evidence-units-v4-20261111/ranked-chunk-text-snapshot.json` identifies
   Safeguarding Policy (Overarching), safeguarding-policy-overarching-v3.docx,
   document `822e205e-2579-4d1c-812d-7882d886bb97`. It says “The site Safeguarding
   Champion” provides local expertise and “The organisation-level Safeguarding
   Lead” oversees the suite and coordinates sharing. Retrieval supplied the distinction.
2. **Confirmed:** n3's user quote is “which current role name and outside-agency
   sharing route should the card use”; its aspect adds “(the organisational role
   name)”, absent from the question. Input-4 b01-01's previously recorded added
   buddy/onboarding examples belong to this same interpretation-drift family;
   its unsupported generation conclusion remains separately owned by Block 3.
3. **Confirmed structural omission:** AssembleGenerationRequest's allowlist omitted
   question_span, and Python GenerationNeed had no such field. The full question
   was still sent, so it is not proved the model saw *only* a paraphrase; the result
   nevertheless follows the added organisation-level reading. Mixed scope composition
   additionally rebuilt the operational question from aspects. Both paths now retain
   user spans. This corrects a contract omission, not a guarantee of model judgment.
4. **Confirmed:** the retained interpretation_check receipt is accepted on attempt
   one. No tuning to that check, changes to its inputs, validators or retry ceiling.

The fix is general: the grounded user span defines requested meaning; the aspect
is retrieval guidance only. Generation and its existing entailment request receive
the span. Same evidence ownership, authority, citations and assembly identity checks.
The additive wire field remains optional for retained v2 requests; current production
needs already require grounded spans and forward them unchanged. Python compatible
reader must be deployed with/before PHP producer. Prompt lineage advances to v11;
result schema/adapter and retrieval/planner remain unchanged.

## Acceptance fixed before the single rerun

b06-06 must distinguish first escalation to organisation Safeguarding Lead and
current site title Safeguarding Champion (renamed from site safeguarding lead),
Finance's record role and the multi-agency sharing route. One role alone fails.
b06-06 is development regression, not independent confirmation. Frozen inputs
2–14 remain unseen confirmation material; no questions or original expectations
are rewritten. Same fourteen inputs, once each, USD 5; four existing omission
probes are retained by the governed stage. Any repeated Block 1 criterion failure
stops without another repair/rerun. No Block 2 work or eligibility change.

Original frozen acceptance: `../block1-closing-20261112/expectations.md`.
Input SHA-256 `507fff88a4e029c11d890efd426a17da8aa5530f5813fa13c942f6096324cbe4`.
All answers/plans require independent review; fake responses establish transport
and invariants only, not live semantic success.
