"""Direct production planner invocation at its existing client seam; no downstream actions."""
import hashlib,json,logging,os,sys,time
from datetime import datetime,timezone
from pathlib import Path
import httpx
from app.settings import get_settings
from app.retrieval.needs import NeedsPlanRequest
from app.retrieval.needs_planner import StructuredChatNeedsPlanner
from app.retrieval.planner import RetrievalPlanningError
from capture import Capture,WORKSPACE,attempts,project,PLAN

def main():
    root=Path(__file__).resolve().parent.parent
    assert root.name=='live' and root.parent.name=='block1-planner-contract-diagnostic-20261114'
    assert os.environ.get('C2_DIAGNOSTIC_AUTH')=='R28-PLANNER-CONTRACT-DIAGNOSTIC-20261114-01'
    item=sys.argv[1]; assert item in ('G2','G3')
    request=NeedsPlanRequest.model_validate_json((root/'requests'/f'{item}.json').read_bytes())
    assert str(request.workspace_id)==WORKSPACE
    original=json.loads((root/'inputs'/f'{item}.json').read_text())
    assert len(request.user_turns)==1 and request.user_turns[0].text==original['utterance']
    logging.disable(logging.CRITICAL)  # Process-local only: no diagnostic output enters application logs.
    ledger=(root/'cases'/f'{item}.ledger.ndjson').open('x'); os.chmod(ledger.name,0o600)
    chain=''
    def emit(row):
        nonlocal chain
        data={'previous_hash':chain,'receipt':row}
        wire=json.dumps(data,separators=(',',':'),ensure_ascii=False)
        chain=hashlib.sha256(wire.encode()).hexdigest()
        ledger.write(json.dumps({**data,'hash':chain},separators=(',',':'),ensure_ascii=False)+'\n');ledger.flush();os.fsync(ledger.fileno())
    cap=Capture(str(request.workspace_id),emit)
    settings=get_settings()
    assert settings.retrieval_planner_provider=='openai' and settings.retrieval_planner_model=='gpt-5-mini'
    start=datetime.now(timezone.utc).isoformat(); timer=time.perf_counter(); accepted=None; failure=None; receipts=[]; stop=False
    with httpx.Client(timeout=settings.retrieval_planner_timeout_seconds,event_hooks={'request':[cap.request],'response':[cap.response]}) as client:
        planner=StructuredChatNeedsPlanner(api_url=settings.retrieval_planner_api_url,api_key=settings.retrieval_planner_api_key,provider_name=settings.retrieval_planner_provider,model=settings.retrieval_planner_model,timeout_seconds=settings.retrieval_planner_timeout_seconds,client=client)
        try:
            result=planner.plan_needs(request.user_turns,evaluated_at=request.evaluated_at.isoformat(),catalogue=request.catalogue)
            accepted=project(result.plan.model_dump(mode='json'),PLAN);receipts=result.usage.validation_attempts
        except RetrievalPlanningError as exc:
            receipts=exc.validation_attempts
            failure={'category':exc.category,'provider_status':exc.provider_status,'systemic':exc.systemic,
                     'validation':project(getattr(exc,'validation',None),{'stage':None,'field':None,'code':None})}
            stop=not (exc.category=='invalid_grounded_needs_plan' and exc.provider_status==200 and not exc.systemic)
        except Exception as exc:
            failure={'category':'harness_or_unknown_failure','exception_type':type(exc).__name__[:80]};stop=True
    for c in cap.calls:
        if c.get('http_status')!=200 or c['status']!='received': stop=True
    total=sum(((c.get('usage') or {}).get('prompt_tokens') or 0)*.25/1e6+((c.get('usage') or {}).get('completion_tokens') or 0)*2/1e6 for c in cap.calls)
    record={'run_id':'R28-PLANNER-CONTRACT-DIAGNOSTIC-20261114-01','item':item,'workspace_id':WORKSPACE,'started_at':start,'ended_at':datetime.now(timezone.utc).isoformat(),'elapsed_ms':(time.perf_counter()-timer)*1000,'accepted_plan':accepted,'failure':failure,'attempts':attempts(cap,receipts),'calls':cap.calls,'recorded_token_cost_estimate_usd':total,'unavailable_usage_calls':[c['call'] for c in cap.calls if c.get('cost_estimate_usd') is None],'invoice_total_available':False,'cost_basis':'retained_2026-08-19_uncached_rates_not_invoice','stop':stop,'original_checkpoint_replaced':False}
    out=root/'cases'/f'{item}.json'
    with out.open('x') as f: json.dump(record,f,indent=2,ensure_ascii=False);f.write('\n')
    os.chmod(out,0o600);ledger.close()
    print(json.dumps({'item':item,'accepted':accepted is not None,'attempts':len(record['attempts']),'cost_estimate_usd':total,'stop':stop}))
    return 4 if stop else 0

if __name__=='__main__':
    try:
        code=main()
    except Exception:
        # Never emit arbitrary exception text, including setup/Pydantic errors.
        print(json.dumps({'stop':True,'category':'diagnostic_setup_or_integrity_failure','details':'unavailable'}))
        code=5
    sys.exit(code)
