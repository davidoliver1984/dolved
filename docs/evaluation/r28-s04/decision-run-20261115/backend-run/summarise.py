"""Read retained execution artefacts only; no grading, providers or writes to raw records."""
import hashlib,json
from pathlib import Path
from collections import Counter
ROOT=Path('/workspace');HERE=Path(__file__).resolve().parent
auth=json.loads((HERE/'authorization.json').read_text())
errors=[];ledger_errors=[]
for path in sorted(HERE.rglob('*.json')):
    if path.name in ('summary.json',):continue
    try:json.loads(path.read_text())
    except json.JSONDecodeError as e:
        errors.append({'file':str(path.relative_to(HERE)),'line':e.lineno,'column':e.colno,'position':e.pos,'message':e.msg,'bytes':path.stat().st_size,'sha256':hashlib.sha256(path.read_bytes()).hexdigest()})
for path in sorted((HERE/'items').glob('*/ledger.ndjson')):
    text=path.read_text(); previous='';line_error=None
    for number,line in enumerate(text.splitlines(),1):
        try:
            event=json.loads(line)
            unhashed=line.rsplit(',"hash":',1)[0]+'}'
            if event['sequence']!=number or event['previous_hash']!=previous or hashlib.sha256(unhashed.encode()).hexdigest()!=event['hash']:
                line_error={'line':number,'kind':'hash_or_sequence_mismatch'};break
            previous=event['hash']
        except json.JSONDecodeError as e:
            line_error={'line':number,'kind':'JSONDecodeError','position':e.pos,'message':e.msg};break
    if line_error:
        # Contrast byte LF records with Python splitlines; diagnosis only, never
        # replace the frozen runner's check or finish its unfinalised item.
        lf_valid=True;prev='';n=0
        for line in text.split('\n'):
            if not line:continue
            n+=1
            try:
                event=json.loads(line);unhashed=line.rsplit(',"hash":',1)[0]+'}'
                lf_valid &= event['sequence']==n and event['previous_hash']==prev and hashlib.sha256(unhashed.encode()).hexdigest()==event['hash']
                prev=event['hash']
            except json.JSONDecodeError:lf_valid=False
        ledger_errors.append({'file':str(path.relative_to(HERE)),'frozen_check_failure':line_error,'LF_delimited_records_parse_and_hash':lf_valid,
                              'non_LF_splitline_characters':{hex(ord(c)):text.count(c) for c in set(text) if c not in '\n\r' and len(('x'+c+'y').splitlines())>1}})
rows=[];statuses={};tokens=Counter();receipt_rows=[]
for ordinal,item in enumerate(auth['items'],1):
    cohort='fresh' if ordinal<=24 else 'control' if ordinal<=28 else 'regression'
    folder=HERE/'items'/item
    row={'ordinal':ordinal,'id':item,'cohort':cohort,'execution':'not_dispatched'}
    if (folder/'raw.json').exists():
        try:r=json.loads((folder/'raw.json').read_text())
        except json.JSONDecodeError:r=None
        if r:
            row.update(execution='recorded' if (HERE/(item+'.record.json')).exists() else 'dispatched_unfinalised',
                production_status=r['status'],run_id=r['run_id'],started_at_london=r['started_at_london'],finished_at_london=r['finished_at_london'],elapsed_ms=r['elapsed_ms'],
                service_calls=len(r.get('service_calls',[])),backend_representations_equal=r['displayed_text']==r['reloaded_rendered_text'],
                persistence_receipt=(folder/'persistence.json').exists(),capture_check_completed=(folder/'capture-check.json').exists(),
                failure_code=r['failure_code'])
            statuses.setdefault(cohort,Counter())[r['status']]+=1
            for call in r.get('service_calls',[]):
                usages=call.get('usage');usages=usages if isinstance(usages,list) else [usages]
                for u in usages:
                    if not isinstance(u,dict):continue
                    for key in ('input_tokens','output_tokens'):
                        if isinstance(u.get(key),int):tokens[key]+=u[key]
                receipt_rows.append({'item':item,'path':call['path'],'request_id':call.get('request_id'),'status':call.get('status'),'http_status':call.get('http_status'),
                     'latency_ms':call.get('latency_ms'),'provider_receipt':call.get('provider_receipt'),'usage':call.get('usage'),
                     'error_category':call.get('error_category'),'planner_detail':call.get('planner_detail')})
            plan_sidecars=[]
            for call in r.get('service_calls',[]):
                for path in sorted((HERE/'capture').glob(str(call.get('request_id'))+'-*.json')):
                    try:s=json.loads(path.read_text())
                    except json.JSONDecodeError:continue
                    plan_sidecars.append({'file':path.name,'stage':s.get('stage'),'attempt':s.get('attempt'),'status':s.get('status'),'validation':s.get('validation')})
            row['attempt_sidecars']=plan_sidecars
            row['sidecar_status_counts']=dict(Counter(s['status'] or 'unspecified' for s in plan_sidecars))
    rows.append(row)
ledger=json.loads((HERE/'accounting.json').read_text())
from decimal import Decimal
known=sum((Decimal(v['known_or_estimated_usd']) for v in ledger['items'].values()),Decimal(0))
unknown=sum((Decimal(v['unknown_liability_usd']) for v in ledger['items'].values()),Decimal(0))
out={'run_id':auth['run_id'],'authorization_id':auth['authorization_id'],'grading':False,'items':rows,'production_status_by_dispatched_cohort':statuses,
     'recorded':sum(r['execution']=='recorded' for r in rows),'dispatched':sum(r['execution']!='not_dispatched' for r in rows),'parse_errors':errors,'ledger_errors':ledger_errors,
     'known_or_retained_rate_subtotal_usd':str(known),'retained_unknown_liability_usd':str(unknown),
     'conservative_committed_usd':ledger['conservative_committed_usd'],'service_receipt_token_subtotal':tokens,
     'accounting_limitations':'Service receipts only; nested aggregates not added. Missing provider receipts/failed attempts are not inferred; full per-item reserve retained.',
     'raw_internal_source_review_json':'unavailable','stop':json.loads((HERE/'runner-stop.json').read_text())}
(HERE/'summary.json').write_text(json.dumps(out,indent=2)+'\n')
(HERE/'service-receipts.json').write_text(json.dumps(receipt_rows,indent=2)+'\n')
lines=['# Per-item execution status (ungraded)','',
       '| Item | Cohort | Execution | Production status | Service calls | Sidecar statuses |',
       '|---|---|---|---|---:|---|']
for row in rows:
    lines.append('| '+ ' | '.join(str(row.get(k,'—')) for k in ('id','cohort','execution','production_status','service_calls','sidecar_status_counts'))+' |')
lines += ['', 'Sidecar statuses are observed attempt receipts, not reconstructed provider attempt counts. Raw per-stage attempts/retry fields, token usage and latency are retained in service-receipts.json; absent fields remain unknown. H2 production completed but its runner capture/integrity finalisation failed. No correctness verdict is assigned.']
(HERE/'per-item-status.md').write_text('\n'.join(lines)+'\n')
print(json.dumps({k:v for k,v in out.items() if k not in ('items',)},indent=2))
