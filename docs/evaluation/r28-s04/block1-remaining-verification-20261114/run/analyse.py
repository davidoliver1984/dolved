"""Provider-free post-run checks and compatible subset metrics; never dispatches."""
import importlib.util,json,sys,re
from pathlib import Path
from statistics import fmean
from collections import Counter
from app.evaluation.models import EvaluationCorpus
from app.retrieval.needs import EvidenceNeed,compiled_request
P=Path(__file__).resolve().parent; R=Path('/workspace/docs/evaluation/r28-s04')
spec=importlib.util.spec_from_file_location('existing_metrics',R/'r07-evidence-unit-metrics-v4-20261111/compute_metrics.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
rows=json.loads((P/'review-inputs.json').read_text())
corpus=EvaluationCorpus.model_validate_json((R/'r07-evidence-units-v4-20261111/evidence-units-corpus.json').read_text())
cases={c.case_id:c for c in corpus.cases}
chunks={c['chunk_id']:c for c in json.loads((P/'review-chunk-snapshot.json').read_text())['rows']}
persistent={r['item']:r for r in json.loads((P/'persistence.json').read_text())['items']}
checks=[];metric_rows=[];excluded=[];flags=[];rejections=[];totals=Counter()
for r in rows:
 raw=json.loads((P/'cases'/f"{r['item']}.json").read_text()); trace=raw['production_trace'] or {}
 saved=persistent[r['item']]
 assert saved['assistant_display_text']==r['answer'] and saved['status']==r['status']
 assert saved['user_turns']==1 and saved['question']==r['question']
 assert saved['workspace_id']=='5323cd6b-8b7a-40f9-98b0-9413c75f643e'
 if saved['reloaded_rendered_text'] is not None: assert saved['reloaded_rendered_text']==r['answer']
 assertions=[]
 for need in (r['plan'] or {}).get('needs',[]):
  parsed=EvidenceNeed.model_validate(need); query=compiled_request(parsed)
  for span in [need['question_span'],*need['context_spans']]: assert span['quote'] in r['question']
  observed=(trace.get(f"need.{need['need_id']}.plan") or {}).get('query')
  if observed is not None: assert observed==query,(r['item'],need['need_id'],observed,query)
  generation=next((n for n in r['generation_needs'] or [] if n['need_id']==need['need_id']),None)
  if generation: assert generation['aspect']==query
  assertions.append({'need_id':need['need_id'],'compiled_request':query,'observed_query':observed,'generation_coverage_label':generation['aspect'] if generation else None})
 attempts=(r['usage'] or {}).get('planner_validation_attempts',[]) or ((r['usage'] or {}).get('planner_failure') or {}).get('validation_attempts',[])
 for a in attempts:
  if a.get('status')=='rejected': rejections.append({'item':r['item'],'validation':a.get('validation'),'rejected_plan':'unavailable'})
  c=a.get('interpretation_check')
  if c:
   for key in ('request_count','input_tokens','output_tokens','latency_ms'): totals[key]+=c.get(key,0) or 0
   if c.get('checker_code')!='accepted': flags.append({'item':r['item'],'check':c})
 checks.append({'item':r['item'],'persistence':'matched','extractive_consumers':assertions,
   'status':r['status'],'literal_no_answer':r['answer']=="I couldn't find enough available evidence to answer that question.",
   'planner_validation_attempts':len((r['usage'] or {}).get('planner_validation_attempts',[])),
   'generation_rejections':[a.get('validation') for a in (r['generation_usage'] or {}).get('validation_attempts',[]) if a.get('outcome')=='rejected']})
 if r['cohort']=='known_regression_ungraded':continue
 if r['cohort']=='development' or not r['expected'] or not r['expected'].get('baseline'):
  excluded.append({'item':r['item'],'reason':'development / no frozen same-case EvidenceUnit baseline'});continue
 if any(k.endswith('topical_current_reuse') for k in trace):
  excluded.append({'item':r['item'],'reason':'C2 reused post-selection candidate subset is not the original pre-threshold reranked list; do not silently score as equivalent'});continue
 attempted,lineage=m.attempted_ranking(trace,chunks)
 units=cases[r['case_id']].evidence_units
 side_metrics={side:m.metric_dict(tuple(u for u in units if u.side==side),[c for c in attempted if c.side==side]) for side in ('PRIMARY','COMPARISON') if any(u.side==side for u in units)}
 baseline=r['expected']['baseline']
 lost={side:sorted(set(baseline['attempted_rerank'][side]['covered_evidence_ids'])-set(v['covered_evidence_ids'])) for side,v in side_metrics.items()}
 metric_rows.append({'item':r['item'],'development':r['item'] in ('31','32'),'case_id':r['case_id'],'reached_rerank':bool(lineage),'baseline_reached_rerank':baseline['reached_rerank'],
  'candidate':side_metrics,'baseline':baseline['attempted_rerank'],'lost_baseline_units':lost,'lineage':lineage,
  'wrong_side':m.wrong_side(units,attempted,[])})
summary={}
for side in ('PRIMARY','COMPARISON'):
 common=[r for r in metric_rows if not r['development'] and r['reached_rerank'] and r['baseline_reached_rerank'] and side in r['candidate']]
 allside=[r for r in metric_rows if not r['development'] and side in r['candidate']]
 summary[side]={}
 for name,rs in [('common_attempted',common),('compatible_subset_including_presearch_zero',allside)]:
  summary[side][name]={'items':[r['item'] for r in rs],'count':len(rs),'baseline':m.means([r['baseline'][side] for r in rs]),'candidate':m.means([r['candidate'][side] for r in rs])}
out={'K':5,'method':'Existing ADR-0020 normalized-token matcher, existing per-need round-robin side ranking; case-first equals variant mean (one variant per case). Compatible subset only, not full32 gate. No causal attribution to wording from uncontrolled model route changes.',
 'summary':summary,'excluded':excluded,'per_case':metric_rows}
(P/sys.argv[1]).write_text(json.dumps(out,indent=2)+'\n')
(P/'post-run-checks.json').write_text(json.dumps({'records':checks,'interpretation_totals':dict(totals),'flags':flags,'planner_rejections':rejections,'status_counts_all18':dict(Counter(r['status'] for r in rows))},indent=2)+'\n')
print(json.dumps({'metrics':summary,'excluded':excluded,'flags':len(flags),'checker_totals':dict(totals),'rejections':rejections},indent=2))
