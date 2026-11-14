"""Post-execution extraction only. Never imported by the dispatch harness."""
import json,hashlib,sys
from pathlib import Path
P=Path(__file__).resolve().parent; R=Path('/workspace')
selection=json.loads((P/'selection.json').read_text())
assert all((P/'cases'/f"{x['id']}.json").exists() for x in selection), 'Review waits for execution to finish'
expect=json.loads((R/'docs/evaluation/r28-s04/block1-extractive-checkpoint-20261113/selection-and-expectations.json').read_text())
expected={x['case_id']:x for x in expect['cases']}
reviews=[]; persistence=[]; chunk_ids=set()
for s in selection:
    r=json.loads((P/'cases'/f"{s['id']}.json").read_text())
    persistence.append({'item':s['id'],'run_id':r['run_id']})
    trace=r.get('production_trace') or {}
    request=r.get('generation_request') or {}
    evidence=request.get('evidence',[])
    for stage,data in trace.items():
        if stage.endswith('.reranked') and isinstance(data,dict):
            chunk_ids.update(c['chunk_id'] for c in data.get('candidates',[]))
    reviews.append({'item':s['id'],'case_id':s.get('case_id'),'cohort':s['cohort'],'question':s['utterance'],'status':r['status'],
        'answer':r.get('displayed_text'),'plan':trace.get('needs_plan'), 'routes':r.get('routes_by_need'),
        'targets':r.get('observed_temporal_targets'),'temporal_context':r.get('temporal_context'),
        'generation_needs':request.get('evidence_needs'),'evidence':evidence,'unsupported_aspects':r.get('unsupported_aspects'),
        'aspect_results':r.get('aspect_results'),'usage':r.get('usage'),'generation_usage':r.get('generation_usage'),
        'expected':expected.get(s.get('case_id')) if s['cohort']!='known_regression_ungraded' else None})
(P/'review-inputs.json').write_text(json.dumps(reviews,indent=2,ensure_ascii=False)+'\n')
(P/'persistence-input.json').write_text(json.dumps({'items':persistence})+'\n')
(P/'ranked-chunk-ids.json').write_text(json.dumps(sorted(chunk_ids))+'\n')
ids=set(sys.argv[1:])
for r in reviews:
    if ids and r['item'] not in ids: continue
    out={k:r[k] for k in ('item','case_id','question','status','answer','unsupported_aspects')}
    out['routes']={k:{'route':v['route'],'decision':(v.get('selection') or {}).get('decision'),'guard':v.get('temporal_guard')} for k,v in (r['routes'] or {}).items()}
    out['evidence']=[{**{k:e.get(k) for k in ('evidence_id','document_chunk_id','document_id','text','side')},'authority':{k:(e.get('temporal_authority') or {}).get(k) for k in ('need_id','document_family_title','document_title','authority_start','authority')}} for e in r['evidence']]
    print(json.dumps(out,ensure_ascii=False))
