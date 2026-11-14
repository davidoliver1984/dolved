# Source-based review: remaining new-runtime cohort

Manual implementing-agent assessment, not independent reviewer confirmation. G2/G3 are excluded. Full frozen expectations, source identities, quoted excerpts and i–iv checks are in `correctness-review.json`; complete source text is in `review-chunk-snapshot.json` and the actual generation requests. No result is inferred solely from a frozen expected label.

| Input | Verdict | Ownership / rationale |
|---|---|---|
| 17 | no-answer | Block 2; initial schema rejection also Block 1 diagnostic: Accepted before-January compare preserves both clauses. Topical reviewer genuinely returned no_match; no family was admitted and no answer invented. The initial temporal_relation_grounding payload is unavailable; its validity cannot be reconstructed. |
| 18 | correct | none; C2 unresolved fallback recorded: Higher of the borderline tiers, nurse/Quality Lead review, and retention of severity classification match the September-effective sources. C2 ambiguity fell back: this is a correct ordinary-path answer, not evidence of temporal guard protection. |
| 19 | no-answer | Block 1/2 shared temporal interpretation and authority-window routing: The update question became compare/historical_reference named February 2026 update, not a transition-period selection. Eligibility returned ambiguous_authority_window_for_period. The February version explicitly says the weeks swapped, so the clarification is a lost answerable request. Do not assign solely downstream: interpretation of the update selector contributes. |
| 20 | correct | none: Names safeguarding champion and explicitly says responsibilities/first-contact function are unchanged; source says precisely that. A source-described change was answered without a historical lane; missing comparison ranking is not itself a wrong answer. |
| 21 | correct | none; C2 family/target route exercised, not distinct-version alternative search: IT service desk now and Training Team before July match both versions. C2 selected the e-learning family and reused eligible current evidence; requested/current and inventoried July targets resolve to the same version. The separate compare need supplies the earlier version. This does not demonstrate the additional distinct-version C1 lane. |
| 22 | correct | none; C2 unresolved fallback recorded: Facilities and quarterly monitoring answer both requested parts from the December-applicable pilot version. Discovery proposed selected but quote_not_in_candidate made the effective decision no_match; the original historical retrieval path ran, with guard explicitly not run. |
| 23 | qualified-correct | Block 2 comparison discovery; Block 3 evidence isolation: HR Director current approval is correct; prior decision is explicitly unconfirmed, not silently omitted. Both reviewer proposals were selected but rejected for quote_not_in_candidate (effective no_match). Current-side chunk 858 names previous hiring-manager discretion, but n2 has no evidence IDs. This exemplifies the existing cross-need support limitation, not proof the corpus lacks history. |
| 24 | wrong | Block 3 source-condition/contact conflation: The answer allows on-call registered manager / senior carer as alternatives to the on-call manager for a failed fridge. The governing breakdown procedure requires BOTH on-call manager and supplying pharmacy. A separate out-of-range table names senior carer but does not authorise substituting that person for the mandatory breakdown call. Current and prior breakdown passages are present and owned by the relevant needs; the April change itself is correctly answered. |
| 25 | correct | none; initial rejected-plan cause unavailable: Ten weeks now versus six before January is supported by current and prior DoLS procedures. First schema rejection temporal_relation_grounding corrected on the existing second attempt; rejected content unavailable. |
| 26 | wrong | Block 2 retrieval/evidence sense plus Block 3 overconfident assembly; not solely Block 3: The encryption control is Device encryption, not mobile device management. The answer also promotes care-related regional on-call mobile numbers as the cyber route; the actual cyber route is regional IT emergency contact for Tier 2+. The compiled needs retain encrypted-device context, but encryption evidence is absent from generation and correct cyber-route evidence belongs to n1/n2 rather than n3. Thus required support/association checks fail; the error is shared upstream/downstream, not an accepted Block 1 pass by attribution. |
| 27 | qualified-correct | Block 2 evidence sense / Block 3 assembly quality limitation: Correct field rename; explicitly declines to invent an Article 6 employment value and discloses two budget-cut eligible units. Retention/reference/right-to-work detail is distracting rather than an answer to the requested field; this is not a complete answer. On this reading it does not claim those details are the missing lawful-basis value. n2 context omits the explicit Legal basis referent, although the full question reaches generation: scope/antecedent adequacy remains a shared limitation, not a clean interpretation success. |
| 28 | wrong | Block 1 role-sense finding with Block 2/3 evidence/assembly contribution; not solely Block 3: Finance record role, escalation to organisation Safeguarding Lead, and external financial-abuse routes are supported. But the separately required current site role Safeguarding Champion is omitted without disclosure; n3 substitutes the escalation-role reading. Unlike the earlier paraphrase failure, compiled n3 now retains the original words, yet no Champion passage reaches n3 generation. The established two-role criterion still fails; renamed-from wording is NOT required. |
| 29 | no-answer | Block 1/C1 temporal-target response-contract failure; scope unresolved, cause not reconstructed: Both generation attempts fail temporal_target_invalid, producing a safe controlled no-answer. The July and current targets resolve to the same Key Safe v2 and its review/removal text is present; Oakfield outreach remains unregistered (resolved location null). Rejected generation content is not retained, so do not infer which target assertion failed or treat this as solely Block 3. No unsafe coverage assertion was published. |
| 30 | correct | none; C2 unresolved fallback recorded: Same-day report to registered manager, cause and action taken match the September-applicable North West pilot. C2 selected proposal fails quote_not_in_candidate, becoming effective no_match; fallback does not claim guard protection. |
| 31 development | no-answer | DEVELOPMENT: Block 1/2 restrictive family binding plus Block 3 citation ownership: Both generation attempts cite outside n2 allowed evidence and are safely rejected. n1 has wellbeing guidance but n2 is bound only to the induction outline, excluding the Staff Handbook passage establishing the induction access check. The correct general question remains extractive, but family selection prevents complete support. Do not assign this solely to generation or count it as independent confirmation. |
| 32 development | correct | DEVELOPMENT: Three completed years means three weeks under the table; equipment/keys/access cards, final holiday pay, HR exit process and consensual exit interview all have source support. Three years is service duration, not an authority date. |

## Input 17: v4.case.corrected-b03-10

Did the Birmingham home need an asbestos-register check before January 2026, and does it now?

### Literal displayed answer

I couldn't find enough available evidence to answer that question.

### Source excerpts

Chunk 683, Asbestos Management Procedure, effective 2026-01-01T00:00:00.000000Z; review-only source, NOT supplied to generation:

> This procedure sets out how Alderbridge Care Group manages a confirmed asbestos register at a site where one applies. It now applies to Riverside House, Moorland View and Oakfield Lodge, following a newly confirmed asbestos survey at Oakfield Lodge; Harbour View, Meadow Court and Willow Bank remain out of scope as newer buildings.

### Attribution

Accepted before-January compare preserves both clauses. Topical reviewer genuinely returned no_match; no family was admitted and no answer invented. The initial temporal_relation_grounding payload is unavailable; its validity cannot be reconstructed.

{"i_request_preservation": true, "ii_authority_scope": "unresolved", "iii_required_source_in_generation": false, "iv_no_upstream_distortion": "not reached"}

## Input 18: v4.case.corrected-b04-05

For a borderline meds error on 3 September 2026, which severity tier should I record and how must that classification be kept?

### Literal displayed answer

If the severity is genuinely borderline, classify it at the higher of the two tiers immediately (i.e., record the incident as the higher severity rather than the lower), and the reviewing registered nurse or the Quality Lead may downgrade it later on fuller review.

Record that classification through the normal incident reporting route (escalate via the Incident Reporting Policy) and retain the incident record and its severity classification according to the activity's record-keeping rules and the Records Retention Schedule.

### Source excerpts

Chunk 863, Medication Policy (Overarching), effective 2025-11-01T00:00:00.000000Z; in generation:

> Where a medication incident's severity is genuinely borderline between two categories, staff classify it at the higher of the two and let the reviewing registered nurse or Quality Lead downgrade it on fuller review, rather than under-classifying an incident that might need a faster initial response.

Chunk 912, Medication Management Manual, effective 2026-08-01T00:00:00.000000Z; in generation:

> Reconciliation records and severity classifications are retained per the Records Retention Schedule.

### Attribution

Higher of the borderline tiers, nurse/Quality Lead review, and retention of severity classification match the September-effective sources. C2 ambiguity fell back: this is a correct ordinary-path answer, not evidence of temporal guard protection.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": true, "iv_no_upstream_distortion": true}

## Input 19: v4.case.corrected-b04-07

What moved between Week 1 and Week 2 of the registered-manager induction in the February 2026 update?

### Literal displayed answer

Which date within that period should I use?

### Source excerpts

Chunk 715, New Registered Manager Induction Outline, effective 2026-02-01T00:00:00.000000Z; review-only source, NOT supplied to generation:

> Supersedes the version effective 1 February 2025. Week 1 and Week 2 content swapped in order; nothing added or removed.

Chunk 715, New Registered Manager Induction Outline, effective 2026-02-01T00:00:00.000000Z; review-only source, NOT supplied to generation:

> Finance and budget training; safeguarding lead responsibilities; HR escalation training.

Chunk 715, New Registered Manager Induction Outline, effective 2026-02-01T00:00:00.000000Z; review-only source, NOT supplied to generation:

> CQC registration responsibilities; meet the regional director; shadow an existing registered manager.

### Attribution

The update question became compare/historical_reference named February 2026 update, not a transition-period selection. Eligibility returned ambiguous_authority_window_for_period. The February version explicitly says the weeks swapped, so the clarification is a lost answerable request. Do not assign solely downstream: interpretation of the update selector contributes.

{"i_request_preservation": true, "ii_authority_scope": false, "iii_required_source_in_generation": false, "iv_no_upstream_distortion": "not reached"}

## Input 20: v4.case.corrected-b04-09

What is the site's safeguarding lead called now, and did the first-contact role itself change?

### Literal displayed answer

The site’s safeguarding lead has been renamed to “safeguarding champion.”

The first-point-of-contact role itself did not change—the responsibilities remain the same under the new title.

### Source excerpts

Chunk 757, Safeguarding Champion Role Guidance, effective 2026-06-01T00:00:00.000000Z; in generation:

> *Supersedes the version effective 1 June 2025. 'Safeguarding lead' renamed to 'Safeguarding champion' throughout — the role and its responsibilities are unchanged.*

Chunk 757, Safeguarding Champion Role Guidance, effective 2026-06-01T00:00:00.000000Z; in generation:

> Each site nominates a safeguarding champion who acts as the first point of contact for safeguarding questions, providing local expertise and support to colleagues raising a concern.

### Attribution

Names safeguarding champion and explicitly says responsibilities/first-contact function are unchanged; source says precisely that. A source-described change was answered without a historical lane; missing comparison ranking is not itself a wrong answer.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": true, "iv_no_upstream_distortion": true}

## Input 21: v4.case.corrected-b05-01

Who do I go to now if I'm locked out of e-learnng or a course is missing, and who handled it before July 2026?

### Literal displayed answer

For current access problems (locked out or a course missing), contact the IT service desk; the Training Team no longer handles platform access issues after the platform migration and support moved to IT.
Temporal basis: 2026-11-14T17:58:55+00:00; version 6cf50cb0-0cdb-484d-9841-899c5e3b84f3; effective 2026-07-01T00:00:00+00:00 to open end (end exclusive).

Before July 2026, access issues were handled by the Training Team—guidance indicated contacting the Training Team by email (internal address shown as training@) for access problems.

### Source excerpts

Chunk 660, E-Learning Platform Guidance, effective 2026-07-01T00:00:00.000000Z; in generation:

> Contact the IT service desk (not the Training Team, which no longer handles platform access issues since the e-learning platform migrated to the same single sign-on as the care records system).

Chunk 419, E-Learning Platform Guidance, effective 2025-07-01T00:00:00.000000Z; in generation:

> Contact the Training Team by email at training@ (internal address) for access issues.

### Attribution

IT service desk now and Training Team before July match both versions. C2 selected the e-learning family and reused eligible current evidence; requested/current and inventoried July targets resolve to the same version. The separate compare need supplies the earlier version. This does not demonstrate the additional distinct-version C1 lane.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": true, "iv_no_upstream_distortion": true}

## Input 22: v4.case.corrected-b05-03

At Riverside House on 15 December 2025, which team monitored the North West key-holder pilot and how often?

### Literal displayed answer

Facilities monitored the North West key-holder pilot and reviewed call-out frequency and outcomes quarterly.

### Source excerpts

Chunk 361, Key Holder Procedure, effective 2025-01-01T00:00:00.000000Z; in generation:

> Facilities reviews call-out frequency and outcomes across the pilot sites quarterly, informing the eventual
> rollout decision.

### Attribution

Facilities and quarterly monitoring answer both requested parts from the December-applicable pilot version. Discovery proposed selected but quote_not_in_candidate made the effective decision no_match; the original historical retrieval path ran, with guard explicitly not run.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": true, "iv_no_upstream_distortion": true}

## Input 23: v4.case.corrected-b05-05

For a hard-to-fill role, who can approve extending the recruitment timescale now, and who held that decision before February 2026?

### Literal displayed answer

The HR Director is the person who now approves or declines requests to extend a recruitment timescale for a genuinely hard-to-fill role; the current Recruitment and Selection Policy requires HR Director sign-off before any extension is made.

Unconfirmed requested aspects

- Who held the decision to extend a recruitment timescale before February 2026 (no evidence_ids were provided for that temporal lane in the package)

### Source excerpts

Chunk 858, Recruitment and Selection Policy, effective 2026-02-01T00:00:00.000000Z; in generation:

> 7. Where a role is genuinely hard to fill and a wider timescale is needed, obtain HR Director sign-off before
> extending it; this is a new requirement replacing the previous hiring manager discretion.

### Attribution

HR Director current approval is correct; prior decision is explicitly unconfirmed, not silently omitted. Both reviewer proposals were selected but rejected for quote_not_in_candidate (effective no_match). Current-side chunk 858 names previous hiring-manager discretion, but n2 has no evidence IDs. This exemplifies the existing cross-need support limitation, not proof the corpus lacks history.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": "current source contains change, not owned by n2", "iv_no_upstream_distortion": "need isolation"}

## Input 24: v4.case.corrected-b05-07

The meds fridge has packed up—who must I call now, and what extra call was added in April 2026?

### Literal displayed answer

If the medicines fridge has failed, staff must keep the fridge closed and contact the on-call manager (or on-call registered manager / senior carer as the first escalation contact) and also call the supplying pharmacy for advice about whether stock remains usable; do not move stock on assumption and follow the Immediate containment steps (note current/minimum/maximum readings, label unit not for use, inform the responsible nurse/manager and prepare a validated alternative storage location where authorised).

The change introduced in April 2026 was the explicit addition of a requirement to call the supplying pharmacy as well as the on-call manager—the earlier version required calling only the on-call manager.

### Source excerpts

Chunk 709, Medication Fridge Breakdown Procedure, effective 2026-04-01T00:00:00.000000Z; in generation:

> Keep the affected fridge closed, do not move stock on assumption, and call both the on-call manager and the supplying pharmacy (a new requirement — the previous version only required the on-call manager) for advice on whether the stock remains usable; see Immediate containment below for the full response, including when a validated alternative storage location may be used.

Chunk 866, Medication Policy (Overarching), effective 2025-11-01T00:00:00.000000Z; in generation:

> Situation First contact Second contact if unresolved
> --- --- ---
> Medication error or near miss Registered nurse or senior carer on duty Registered manager
> Fridge temperature out of range Senior carer on duty Facilities and Medication Fridge Breakdown Procedure
> Suspected covert administration need GP and registered nurse Multidisciplinary team best-interests meeting
> Medication-related safeguarding concern Safeguarding Lead directly Registered manager

### Attribution

The answer allows on-call registered manager / senior carer as alternatives to the on-call manager for a failed fridge. The governing breakdown procedure requires BOTH on-call manager and supplying pharmacy. A separate out-of-range table names senior carer but does not authorise substituting that person for the mandatory breakdown call. Current and prior breakdown passages are present and owned by the relevant needs; the April change itself is correctly answered.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": true, "iv_no_upstream_distortion": true}

## Input 25: v4.case.corrected-b05-09

How many weeks before a DoLS expiry should I flag the renewal now, compared with the rule before January 2026?

### Literal displayed answer

Now (current procedure), flag any authorisation expiring within 10 weeks to the local authority.

Before January 2026 (earlier version), flag any authorisation expiring within 6 weeks to the local authority.

### Source excerpts

Chunk 693, DoLS Renewal Tracking Procedure, effective 2026-01-01T00:00:00.000000Z; in generation:

> The registered manager reviews the DoLS tracker monthly and flags any authorisation expiring within 10 weeks (widened from 6 weeks) to the local authority.

Chunk 452, DoLS Renewal Tracking Procedure, effective 2025-01-01T00:00:00.000000Z; in generation:

> The registered manager reviews the DoLS tracker monthly and flags any authorisation expiring within 6 weeks to the local authority.

### Attribution

Ten weeks now versus six before January is supported by current and prior DoLS procedures. First schema rejection temporal_relation_grounding corrected on the existing second attempt; rejected content unavailable.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": true, "iv_no_upstream_distortion": true}

## Input 26: v4.case.corrected-b06-04

I'm updating our response card for a lost encrypted work device. What is the control called now, and who classifies the incident, how quickly must the DPO assess it, and which out-of-hours route was added?

### Literal displayed answer

The device-control is implemented via the organisation's mobile device management — devices must be enrolled in the mobile device management system — and IT is responsible for classifying incidents against the four-tier severity model.

The Data Protection Officer (DPO) must assess whether the incident is a personal data breach within 12 hours.

The out-of-hours route now uses regional on-call mobile numbers (one per region) so staff call their region's on-call mobile number outside normal hours; additionally, the procedures reference contacting the regional IT emergency contact for out-of-hours support at Tier 2 or higher.

### Source excerpts

Chunk 710, Data Encryption Standard, effective 2026-01-01T00:00:00.000000Z; review-only source, NOT supplied to generation:

> Supersedes the version effective 1 January 2025. 'Laptop encryption' renamed to 'Device encryption' to reflect coverage of tablets as well as laptops — no requirement changed for laptops specifically.

Chunk 849, Cyber Incident Response Plan, effective 2026-10-01T00:00:00.000000Z; in generation:

> Contact the regional IT emergency contact for out-of-hours support at a Tier 2 or higher incident.

Chunk 674, On-Call Manager Procedure, effective 2026-06-01T00:00:00.000000Z; in generation:

> Supersedes the version effective 1 June 2025. Replaces the single central on-call number with a regional on-call structure (three numbers, one per region).

### Attribution

The encryption control is Device encryption, not mobile device management. The answer also promotes care-related regional on-call mobile numbers as the cyber route; the actual cyber route is regional IT emergency contact for Tier 2+. The compiled needs retain encrypted-device context, but encryption evidence is absent from generation and correct cyber-route evidence belongs to n1/n2 rather than n3. Thus required support/association checks fail; the error is shared upstream/downstream, not an accepted Block 1 pass by attribution.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": false, "iv_no_upstream_distortion": false}

## Input 27: v4.case.corrected-b06-05

I'm updating the ROPA for employee records. What did the old Legal basis field become, and what should I record there for employment data?

### Literal displayed answer

The ROPA field previously labelled "Legal basis" was renamed to "Lawful basis (UK GDPR Article 6)".

For employment-related processing, record the relevant processing activity and the category of employment data being processed and link it to operational practices shown in the policies: employee personnel files are a named record type with a retention period of 6 years after employment ends (basis: statute of limitations), recruitment records for unsuccessful candidates are listed (6 months), HR issues factual references confirming dates of employment and job title within a 5-working-day timeframe, and right-to-work document checks are completed, dated/annotated and retained per the Records Retention Schedule. The supplied evidence does not state which specific Article 6 lawful basis value (for example, contract, legal obligation, legitimate interests, etc.) to enter in the ROPA field.

Unconfirmed requested aspects

- Not established from the selected evidence: I'm updating the ROPA for employee records. what should I record there for employment data?. Additional eligible evidence was omitted because the answer evidence budget was reached. This does not establish that the source documents lack the requested information.

### Source excerpts

Chunk 692, Record of Processing Activities Form, effective 2026-05-25T00:00:00.000000Z; in generation:

> Supersedes the version effective 25 May 2025. 'Legal basis' field renamed to 'Lawful basis (UK GDPR Article 6)' for precision.

### Attribution

Correct field rename; explicitly declines to invent an Article 6 employment value and discloses two budget-cut eligible units. Retention/reference/right-to-work detail is distracting rather than an answer to the requested field; this is not a complete answer. On this reading it does not claim those details are the missing lawful-basis value. n2 context omits the explicit Legal basis referent, although the full question reaches generation: scope/antecedent adequacy remains a shared limitation, not a clean interpretation success.

{"i_request_preservation": "partly", "ii_authority_scope": true, "iii_required_source_in_generation": false, "iv_no_upstream_distortion": false}

## Input 28: v4.case.corrected-b06-06

I'm updating our safeguarding card for a resident-money discrepancy. Who receives the first escalation now, what part does Finance keep, and which current role name and outside-agency sharing route should the card use?

### Literal displayed answer

Escalate the discrepancy to the Alderbridge Safeguarding Lead as the first contact — the policy replaces the previous Finance Manager-first route and requires immediate escalation to the Safeguarding Lead for a financial concern.

Finance retains responsibility for the financial-record and audit aspects: the Finance Manager is brought in to support the financial record review once the Safeguarding Lead has taken the concern forward, and resident fund transaction records, appointeeship files and audit findings are kept per the Records Retention Schedule; the Finance Manager also audits resident fund records quarterly.

Use the organisation-level Safeguarding Lead as the role name on the card for internal escalation, and include the external agency sharing routes now specified for financial abuse concerns — namely referral routes to the Office of the Public Guardian (where an attorney or deputy may be acting outside their authority) and to Action Fraud (for suspected fraud or scam); also retain the local authority safeguarding team referral route where the local threshold is met.

### Source excerpts

Chunk 697, Resident Finances and Appointeeship Policy, effective 2026-03-01T00:00:00.000000Z; in generation:

> **Why was the escalation contact changed to the Safeguarding Lead?** A review found routing a financial
> concern through the Finance Manager first sometimes delayed the safeguarding assessment that
> ultimately mattered most; escalating directly to the Safeguarding Lead removes that delay. **Does the
> Finance Manager still have a role?** Yes, they support the financial record and audit aspect once the
> Safeguarding Lead has taken the concern forward, but no longer receive the initial escalation.

Chunk 934, Multi-Agency Safeguarding Protocol, effective 2025-12-01T00:00:00.000000Z; in generation:

> Supersedes the version effective 1 November 2024. Adds the Office of the Public Guardian and Action
> Fraud as explicit external agency routes for a financial abuse concern, aligning with the Safeguarding
> Policy (Overarching)'s own formal recognition of financial abuse.

Chunk 757, Safeguarding Champion Role Guidance, effective 2026-06-01T00:00:00.000000Z; review-only source, NOT supplied to generation:

> Each site nominates a safeguarding champion who acts as the first point of contact for safeguarding questions, providing local expertise and support to colleagues raising a concern.

### Attribution

Finance record role, escalation to organisation Safeguarding Lead, and external financial-abuse routes are supported. But the separately required current site role Safeguarding Champion is omitted without disclosure; n3 substitutes the escalation-role reading. Unlike the earlier paraphrase failure, compiled n3 now retains the original words, yet no Champion passage reaches n3 generation. The established two-role criterion still fails; renamed-from wording is NOT required.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": false, "iv_no_upstream_distortion": false}

## Input 29: v4.case.corrected-b06-11

At Oakfield outreach on 1 July 2026, when did a key safe need reviewing and what had to happen when it was no longer needed?

### Literal displayed answer

I couldn't find enough available evidence to answer that question.

### Source excerpts

Chunk 702, Key Safe Procedure, effective 2026-02-01T00:00:00.000000Z; in generation:

> The responsible manager reviews continuing need, authorised recipients and physical condition at least annually and after a tenancy, care-package or risk change. When the safe is no longer required it is removed or disabled promptly, keys are returned and access records are closed. Installation, code-change, incident, review and removal evidence is retained securely.

### Attribution

Both generation attempts fail temporal_target_invalid, producing a safe controlled no-answer. The July and current targets resolve to the same Key Safe v2 and its review/removal text is present; Oakfield outreach remains unregistered (resolved location null). Rejected generation content is not retained, so do not infer which target assertion failed or treat this as solely Block 3. No unsafe coverage assertion was published.

{"i_request_preservation": true, "ii_authority_scope": "version correct; location coverage unresolved", "iii_required_source_in_generation": true, "iv_no_upstream_distortion": "target output unavailable"}

## Input 30: v4.case.corrected-b06-12

At Riverside House in September 2025, when did I have to report a key-holder call-out and what details belonged in the report?

### Literal displayed answer

Report every key-holder call-out to the registered manager the same day, and include the cause of the activation and any action taken.

### Source excerpts

Chunk 361, Key Holder Procedure, effective 2025-01-01T00:00:00.000000Z; in generation:

> 4. Report every call-out to the registered manager the same day, including the cause and any action
> taken.

### Attribution

Same-day report to registered manager, cause and action taken match the September-applicable North West pilot. C2 selected proposal fails quote_not_in_candidate, becoming effective no_match; fallback does not claim guard protection.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": true, "iv_no_upstream_distortion": true}

## Input 31: v4.case.corrected-b01-01

As a new starter, which wellbeing services should I know about, and how will my induction check that I know how to access them?

### Literal displayed answer

I couldn't find enough available evidence to answer that question.

### Source excerpts

Chunk 69, Staff Wellbeing Guidance, effective 2026-01-06T00:00:00.000000Z; in generation:

> **Employee Assistance Programme (EAP)** a confidential, externally provided counselling and support service available to every employee. **Mental health first aider** a colleague trained to provide an initial supportive conversation and signpost further help. **Wellbeing referral** a manager-initiated referral to occupational health where attendance or performance appears affected by a health condition.

Chunk 323, New Starter Induction Programme Outline, effective 2025-09-01T00:00:00.000000Z; in generation:

> Day 5: Meet the registered manager, questions, probation review date confirmed

Chunk 962, Staff Handbook Full Manual, effective 2025-10-01T00:00:00.000000Z; review-only source, NOT supplied to generation:

> A new starter is asked, as part of their induction check-in, a simple question confirming they know how to access the Employee Assistance Programme and who to contact for an occupational health referral, a small but deliberate check that the new consolidated summary has actually landed rather than being read once and forgotten.

### Attribution

Both generation attempts cite outside n2 allowed evidence and are safely rejected. n1 has wellbeing guidance but n2 is bound only to the induction outline, excluding the Staff Handbook passage establishing the induction access check. The correct general question remains extractive, but family selection prevents complete support. Do not assign this solely to generation or count it as independent confirmation.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": false, "iv_no_upstream_distortion": false}

## Input 32: v4.case.corrected-b03-05

I'm resigning after three years. How much notice do I give, and what else do I need to do before I leave?

### Literal displayed answer

For three years' service the handbook's notice table applies: for 2 years or more the required notice is one week per completed year, so after three years you give three weeks' notice.

When you resign you should give notice as above, and complete the usual leaving steps: return any issued equipment, keys or access cards; HR will run the exit process including a final pay calculation and (where you agree) an exit interview; and you will receive a final payslip that reflects any outstanding holiday pay.

### Source excerpts

Chunk 980, Employee Handbook — Terms and Conditions, effective 2026-07-01T00:00:00.000000Z; in generation:

> Length of service	Notice period (either party)
> During probationary period	1 week
> Up to 2 years	1 week per completed year, minimum 1 week
> 2 years or more	1 week per completed year, up to a statutory maximum

Chunk 981, Employee Handbook — Terms and Conditions, effective 2026-07-01T00:00:00.000000Z; in generation:

> A staff member resigning gives notice per the table above, returns any issued equipment, key or access card, and receives a final payslip reflecting any outstanding holiday pay.

Chunk 957, Staff Handbook Full Manual, effective 2025-10-01T00:00:00.000000Z; in generation:

> An employee wishing to resign provides notice per their own contract; Human Resources conducts an exit process covering the return of Alderbridge property, a final pay calculation, and, where the employee agrees, an exit interview.

### Attribution

Three completed years means three weeks under the table; equipment/keys/access cards, final holiday pay, HR exit process and consensual exit interview all have source support. Three years is service duration, not an authority date.

{"i_request_preservation": true, "ii_authority_scope": true, "iii_required_source_in_generation": true, "iv_no_upstream_distortion": true}
