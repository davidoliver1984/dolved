"""Offline reconciliation only; preserves the three raw dispatch packages."""
import json
from pathlib import Path
HERE = Path(__file__).resolve().parent
BASE = HERE.parent
def read(path): return json.loads(path.read_text())
def write(name, value): (HERE/name).write_text(json.dumps(value, indent=2, ensure_ascii=False)+'\n')
review = read(HERE/'correctness-review.json')
attributions = {
7: {'owner':'Block 2', 'i':'Full question, both deadline and review step, preserved in compiled request; compare operation.', 'ii':'Transition_period 2025-11 grounded in November 2025; no version pair admitted because topical review selected was rejected quote_not_in_candidate.', 'iii':'No final evidence or generation input; cannot claim adequate evidence reached generation.', 'iv':'Generation not reached. Earliest rejection is topical-family quotation validation, not answer assembly.', 'limit':'The retained diagnostic identifies this current rejection; it does not retrospectively identify the three historical effective no_match causes.'},
10: {'owner':'Block 3; draft-versus-validator cause unestablished', 'i':'Compiled request retains 1 July 2025, Riverside House, required count and both-away contingency.', 'ii':'authority=date 2025-07-01; selected Key Holder Procedure v1 fa86860e-8e8d-4df3-b4c2-85b9e45b39ef, applicable Riverside/North West scope.', 'iii':'generation_input includes 230ad044-f514-5a70-b63d-a6e7b9eb67c2 and 6d905201-e4a0-5267-9284-2f3351d46b0c: two nominees and neighbouring North West temporary cover. Full passages quoted in case review.', 'iv':'Single n1 owned source passages; assembly forwards whole text, grounded question_span and compiled request with dated authority, no paraphrased aspect. See pinned assembly references in report.', 'limit':'Two entailment_uncertain rejection receipts, no retained rejected drafts. Cannot decide bad draft versus overcautious review. Fallback is misleading because required evidence was found.'},
15: {'owner':'Block 3', 'i':'Single compiled request equals the full user question, preserving nonacknowledgment, next action and retained record. No narrowing context.', 'ii':'Current/generic matches the undated organisational procedure question. Current MultiAgency v3 and retention source selected.', 'iii':'generation_input explicitly contains 8899d493-216d-5695-a0b0-094d4fde1129 with within its own stated timescale, plus checklist and retention passages quoted in review.', 'iv':'Single n1, complete whole source text, no borrowed need or paraphrase. Current authority and original user span forwarded. Prompt requires supported claims and does not license dropping source conditions.', 'limit':'Trace records evidence IDs, not the full HTTP request. Whole-text/source/need linkage is established by pinned assembly code and frozen source snapshot. Entailment accepted the output; omitted condition is directly visible.'},
16: {'owner':'Block 1; contributing generation-validation behaviour unresolved/shared', 'i':'Compiled words preserve the full date, site and supplier question. Word presence is not semantic authority coverage.', 'ii':'FAIL: authority=current; 2026-02-01 is only fact_reference, and need.n1.plan is temporal_mode=current, explicit_date=null. Current v2 effective 2026-06-01 is after the requested 2026-02-01. Historical v1 effective 2025-06-01 is not in eligibility or final evidence.', 'iii':'FAIL: generation_input contains current v2 42cc2beb-792a-5415-bbda-9565483900e9, not v1 ef285503-119f-500d-91b8-0a3f597872a9. Frozen read-only source/metadata confirms both.', 'iv':'FAIL upstream temporal metadata: per-need current authority is passed with the February question. No evidence the diagnostic model aspect executed; distortion is typed authority, not query wording.', 'limit':'Two entailment_uncertain rejections and no drafts retained. Cannot prove correcting authority alone would make generation succeed. Earliest evidenced error is planner date-role classification, passed by checker and faithfully executed. This cannot be assigned solely to Block 3 and triggers HOLD.'}}
for row in review['cases']:
    if row['input'] in attributions: row['attribution_i_to_iv'] = attributions[row['input']]
    if row['input']==10: row['reason']='Required dated evidence reached generation; two entailment_uncertain rejections. Draft fault versus overcautious validator remains unestablished. The evidence-absence fallback is misleading. This corrects the historical description, not its immutable record or no-answer verdict.'
write('correctness-review.json',review)
results=read(HERE/'results-a.json')
assert (HERE/'results-a.json').read_bytes()==(HERE/'results-b.json').read_bytes()
selection={c['case_id']:c for c in read(BASE/'block1-extractive-checkpoint-20261113/selection-and-expectations.json')['cases']}
corpus={c['case_id']:c for c in read(BASE/'r07-evidence-units-v4-20261111/evidence-units-corpus.json')['cases']}
missing=[]
for row in results['cases']:
    for side, metric in row['attempted_rerank'].items():
        prior=set(selection[row['case_id']]['baseline']['attempted_rerank'].get(side,{}).get('covered_evidence_ids',[]))
        covered=set(metric['covered_evidence_ids'])
        for unit in corpus[row['case_id']]['evidence_units']:
            if unit['side']==side and unit['evidence_id'] not in covered:
                missing.append({'case_id':row['case_id'],'side':side,'unit':unit,'lost_from_baseline':unit['evidence_id'] in prior,'significance': 'Authority-critical historical supplier identity replaced by a post-date current version; blocks regardless of aggregate.' if row['case_id'].endswith('b03-07') else 'Existing miss, not a newly lost unit; see source-based case verdict and Block 2 attribution where applicable.'})
write('missing-and-lost-units.json',{'K':5,'observed_records':16,'unattempted_not_scored':16,'units':missing,'newly_lost_count':sum(x['lost_from_baseline'] for x in missing),'deterministic_results_byte_identical':True})
print('Reconciled 16 verdicts, 16 unattempted; explicit attributions and lost-unit inventory.')
