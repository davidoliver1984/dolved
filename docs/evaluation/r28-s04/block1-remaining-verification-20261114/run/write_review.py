"""Render the implementing agent's manual source review; no model judge or dispatch."""
import json
from pathlib import Path
from collections import Counter
P=Path(__file__).resolve().parent; R=Path('/workspace/docs/evaluation/r28-s04')
rows=json.loads((P/'review-inputs.json').read_text())
snapshot=json.loads((P/'review-chunk-snapshot.json').read_text())['rows']
byid={r['numeric_chunk_id']:r for r in snapshot}
corpus=json.loads((R/'r07-evidence-units-v4-20261111/evidence-units-corpus.json').read_text())
expected={c['case_id']:c for c in corpus['cases']}
# Reviewed manually against the complete questions, displayed answers, plans,
# per-need generation evidence, source text and persisted receipts. Not label scoring.
judgments={
'17':('no-answer','Block 2; initial schema rejection also Block 1 diagnostic',
 'Accepted before-January compare preserves both clauses. Topical reviewer genuinely returned no_match; no family was admitted and no answer invented. The initial temporal_relation_grounding payload is unavailable; its validity cannot be reconstructed.',
 [True,'unresolved',False,'not reached'],[(683,'It now applies to Riverside House')]),
'18':('correct','none; C2 unresolved fallback recorded',
 'Higher of the borderline tiers, nurse/Quality Lead review, and retention of severity classification match the September-effective sources. C2 ambiguity fell back: this is a correct ordinary-path answer, not evidence of temporal guard protection.',
 [True,True,True,True],[(863,'Where a medication incident'),(912,'Reconciliation records and severity classifications')]),
'19':('no-answer','Block 1/2 shared temporal interpretation and authority-window routing',
 'The update question became compare/historical_reference named February 2026 update, not a transition-period selection. Eligibility returned ambiguous_authority_window_for_period. The February version explicitly says the weeks swapped, so the clarification is a lost answerable request. Do not assign solely downstream: interpretation of the update selector contributes.',
 [True,False,False,'not reached'],[(715,'Week 1 and Week 2 content swapped'),(715,'Finance and budget training'),(715,'CQC registration responsibilities')]),
'20':('correct','none',
 'Names safeguarding champion and explicitly says responsibilities/first-contact function are unchanged; source says precisely that. A source-described change was answered without a historical lane; missing comparison ranking is not itself a wrong answer.',
 [True,True,True,True],[(757,"'Safeguarding lead' renamed"),(757,'Each site nominates')]),
'21':('correct','none; C2 family/target route exercised, not distinct-version alternative search',
 'IT service desk now and Training Team before July match both versions. C2 selected the e-learning family and reused eligible current evidence; requested/current and inventoried July targets resolve to the same version. The separate compare need supplies the earlier version. This does not demonstrate the additional distinct-version C1 lane.',
 [True,True,True,True],[(660,'Contact the IT service desk'),(419,'Training Team')]),
'22':('correct','none; C2 unresolved fallback recorded',
 'Facilities and quarterly monitoring answer both requested parts from the December-applicable pilot version. Discovery proposed selected but quote_not_in_candidate made the effective decision no_match; the original historical retrieval path ran, with guard explicitly not run.',
 [True,True,True,True],[(361,'Facilities reviews call-out frequency')]),
'23':('qualified-correct','Block 2 comparison discovery; Block 3 evidence isolation',
 'HR Director current approval is correct; prior decision is explicitly unconfirmed, not silently omitted. Both reviewer proposals were selected but rejected for quote_not_in_candidate (effective no_match). Current-side chunk 858 names previous hiring-manager discretion, but n2 has no evidence IDs. This exemplifies the existing cross-need support limitation, not proof the corpus lacks history.',
 [True,True,'current source contains change, not owned by n2','need isolation'],[(858,'Where a role is genuinely hard to fill')]),
'24':('wrong','Block 3 source-condition/contact conflation',
 'The answer allows on-call registered manager / senior carer as alternatives to the on-call manager for a failed fridge. The governing breakdown procedure requires BOTH on-call manager and supplying pharmacy. A separate out-of-range table names senior carer but does not authorise substituting that person for the mandatory breakdown call. Current and prior breakdown passages are present and owned by the relevant needs; the April change itself is correctly answered.',
 [True,True,True,True],[(709,'call both the on-call manager'),(866,'Fridge temperature out of range')]),
'25':('correct','none; initial rejected-plan cause unavailable',
 'Ten weeks now versus six before January is supported by current and prior DoLS procedures. First schema rejection temporal_relation_grounding corrected on the existing second attempt; rejected content unavailable.',
 [True,True,True,True],[(693,'flags any authorisation expiring within 10 weeks'),(452,'flags any authorisation expiring within 6 weeks')]),
'26':('wrong','Block 2 retrieval/evidence sense plus Block 3 overconfident assembly; not solely Block 3',
 'The encryption control is Device encryption, not mobile device management. The answer also promotes care-related regional on-call mobile numbers as the cyber route; the actual cyber route is regional IT emergency contact for Tier 2+. The compiled needs retain encrypted-device context, but encryption evidence is absent from generation and correct cyber-route evidence belongs to n1/n2 rather than n3. Thus required support/association checks fail; the error is shared upstream/downstream, not an accepted Block 1 pass by attribution.',
 [True,True,False,False],[(710,"'Laptop encryption' renamed"),(849,'Contact the regional IT emergency contact'),(674,'regional')]),
'27':('qualified-correct','Block 2 evidence sense / Block 3 assembly quality limitation',
 'Correct field rename; explicitly declines to invent an Article 6 employment value and discloses two budget-cut eligible units. Retention/reference/right-to-work detail is distracting rather than an answer to the requested field; this is not a complete answer. On this reading it does not claim those details are the missing lawful-basis value. n2 context omits the explicit Legal basis referent, although the full question reaches generation: scope/antecedent adequacy remains a shared limitation, not a clean interpretation success.',
 ['partly',True,False,False],[(692,"'Legal basis' field renamed")]),
'28':('wrong','Block 1 role-sense finding with Block 2/3 evidence/assembly contribution; not solely Block 3',
 'Finance record role, escalation to organisation Safeguarding Lead, and external financial-abuse routes are supported. But the separately required current site role Safeguarding Champion is omitted without disclosure; n3 substitutes the escalation-role reading. Unlike the earlier paraphrase failure, compiled n3 now retains the original words, yet no Champion passage reaches n3 generation. The established two-role criterion still fails; renamed-from wording is NOT required.',
 [True,True,False,False],[(697,'Does the'),(934,'Office of the Public Guardian'),(757,'Each site nominates')]),
'29':('no-answer','Block 1/C1 temporal-target response-contract failure; scope unresolved, cause not reconstructed',
 'Both generation attempts fail temporal_target_invalid, producing a safe controlled no-answer. The July and current targets resolve to the same Key Safe v2 and its review/removal text is present; Oakfield outreach remains unregistered (resolved location null). Rejected generation content is not retained, so do not infer which target assertion failed or treat this as solely Block 3. No unsafe coverage assertion was published.',
 [True,'version correct; location coverage unresolved',True,'target output unavailable'],[(702,'The responsible manager reviews')]),
'30':('correct','none; C2 unresolved fallback recorded',
 'Same-day report to registered manager, cause and action taken match the September-applicable North West pilot. C2 selected proposal fails quote_not_in_candidate, becoming effective no_match; fallback does not claim guard protection.',
 [True,True,True,True],[(361,'Report every call-out')]),
'31':('no-answer','DEVELOPMENT: Block 1/2 restrictive family binding plus Block 3 citation ownership',
 'Both generation attempts cite outside n2 allowed evidence and are safely rejected. n1 has wellbeing guidance but n2 is bound only to the induction outline, excluding the Staff Handbook passage establishing the induction access check. The correct general question remains extractive, but family selection prevents complete support. Do not assign this solely to generation or count it as independent confirmation.',
 [True,True,False,False],[(69,'Employee Assistance Programme'),(323,'Day 5'),(962,'A new starter is asked')]),
'32':('correct','DEVELOPMENT',
 'Three completed years means three weeks under the table; equipment/keys/access cards, final holiday pay, HR exit process and consensual exit interview all have source support. Three years is service duration, not an authority date.',
 [True,True,True,True],[(980,'2 years or more'),(981,'A staff member resigning'),(957,'exit')]),
}
def quote(cid,needle):
 row=byid[cid]; paragraphs=row['text'].split('\n\n')
 text=next(p for p in paragraphs if needle.lower() in p.lower())
 return {**{k:row[k] for k in ('chunk_id','numeric_chunk_id','document_id','numeric_document_id','family_title','effective_from')},'quote':text}
out=[]
for r in rows:
 if r['item'] not in judgments:continue
 verdict,owner,reason,stages,sources=judgments[r['item']]
 units=expected.get(r['case_id'],{}).get('evidence_units',[])
 citations=[quote(*s) for s in sources]
 for s in citations:s['in_actual_generation_input']=any(e['document_chunk_id']==s['numeric_chunk_id'] for e in r['evidence'])
 out.append({'item':r['item'],'development':r['item'] in ('31','32'),'case_id':r['case_id'],'question':r['question'],
 'displayed_text':r['answer'],'status':r['status'],'verdict':verdict,'owning_block_or_disposition':owner,'rationale':reason,
 'stage_attribution':dict(zip(['i_request_preservation','ii_authority_scope','iii_required_source_in_generation','iv_no_upstream_distortion'],stages)),
 'source_quotes':citations,'frozen_expectation':r['expected'],'frozen_required_units':units,
 'routes':r['routes'],'unsupported_aspects':r['unsupported_aspects']})
summary={'all16':dict(Counter(r['verdict'] for r in out)),
 'nondevelopment14':dict(Counter(r['verdict'] for r in out if not r['development'])),
 'development2':dict(Counter(r['verdict'] for r in out if r['development']))}
(P/'correctness-review.json').write_text(json.dumps({'reviewer':'implementing Codex agent, manual source-based review; not an independent human judgment or a model-judge scoring call','summary':summary,'cases':out},indent=2,ensure_ascii=False)+'\n')
lines=['# Source-based review: remaining new-runtime cohort','',
 'Manual implementing-agent assessment, not independent reviewer confirmation. G2/G3 are excluded. Full frozen expectations, source identities, quoted excerpts and i–iv checks are in `correctness-review.json`; complete source text is in `review-chunk-snapshot.json` and the actual generation requests. No result is inferred solely from a frozen expected label.','',
 '| Input | Verdict | Ownership / rationale |','|---|---|---|']
for r in out: lines.append(f"| {r['item']}{' development' if r['development'] else ''} | {r['verdict']} | {r['owning_block_or_disposition']}: {r['rationale']} |")
for r in out:
 lines.extend(['',f"## Input {r['item']}: {r['case_id']}",'',r['question'],'','### Literal displayed answer','',r['displayed_text'],'','### Source excerpts',''])
 for s in r['source_quotes']:
  lines.extend([f"Chunk {s['numeric_chunk_id']}, {s['family_title']}, effective {s['effective_from']}; {'in generation' if s['in_actual_generation_input'] else 'review-only source, NOT supplied to generation'}:",'',*['> '+l for l in s['quote'].splitlines()],''])
 lines.extend(['### Attribution','',r['rationale'],'',json.dumps(r['stage_attribution'],ensure_ascii=False)])
(P/'correctness-review.md').write_text('\n'.join(lines)+'\n')
print(json.dumps(summary,indent=2))
