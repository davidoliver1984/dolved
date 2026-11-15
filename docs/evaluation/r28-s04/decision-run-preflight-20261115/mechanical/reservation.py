"""Revised single-turn envelope; no pricing/provider requests or authority creation."""
import hashlib
import json
from pathlib import Path
import sys
ROOT=Path('/workspace');HERE=Path(__file__).resolve().parent
sys.path.insert(0,str(HERE.parent/'completion'))
from released_input import parse_release
from app.retrieval.compare_family import PROMPT
raw=(ROOT/'docs/evaluation/r28-s04/decision-run-20261115/released-input/questions-only.json').read_bytes()
items=parse_release(raw)
longest=max(len(i['utterance'].encode()) for i in items)
query=9*longest+8
assert longest==218 and query==1970
# Bound all candidate variable text by worst JSON escaping, not English tokens.
candidates=[{'family_id':f'00000000-0000-4000-8000-{i:012d}','chunk_id':f'10000000-0000-4000-8000-{i:012d}',
             'title':'\x01'*255,'text':'\x01'*2000} for i in range(12)]
body=json.dumps({'model':'gpt-5-mini','max_completion_tokens':4096,'reasoning_effort':'low',
    'messages':[{'role':'system','content':PROMPT},{'role':'user','content':json.dumps({'question':'\x01'*query,'candidates':candidates},ensure_ascii=False)}]},ensure_ascii=False)
# Serialized outer JSON double-escapes inner JSON; conservative schema/framing reserve.
family_bytes=len(body.encode());family_overhead=16384
family_bound=family_bytes+family_overhead
rows=[]
def stage(name,calls,ins,outs,ri=.25,ro=2,notes=''):
    cost=34*calls*(ins*ri+outs*ro)/1e6
    rows.append({'stage':name,'max_calls_per_item':calls,'input_tokens_per_call':ins,'output_tokens_per_call':outs,
                 'input_rate_per_million':ri,'output_rate_per_million':ro,'all_34_usd':cost,'basis':notes})
stage('planner',4,104096,25000,notes='Enforced complete request.content <=100000 bytes +4096 provider framing tokens. Up to2 outer x2 inner attempts.')
stage('interpretation_check',4,400000,4096,notes='Retain whole-context allowance: complete checker plan serialization not independently bounded more tightly here.')
stage('topical_family_review',6,family_bound,4096,notes='12 maximum-sized candidates; worst JSON escaping incl outer request; extra16384 covers small fixed schema, UUID/response format metadata and provider framing; up to3 needs x2 transports.')
stage('coverage_selector',1,30000+4096,4096,notes='Enforced30000 metered input plus4096 explicit schema/framing uncertainty; no retry.')
stage('generation',6,400000,4096,notes='Retain whole context; 2 validation x3 transport, SDK retries0.')
stage('source_review_entailment',6,400000,17408,notes='One sequential review call, not a separate entailment charge; review failure can repeat generation.')
stage('embedding',80,query+128,0,.12,0,notes='10 conservative search units x2 enclosing x4 embedding; single-turn9 grounded spans;128 framing allowance.')
max_chunk=json.loads((HERE.parent/'verification/corpus-bounds.json').read_text())['corpus_bounds']['max_text_bytes']
stage('reranking',60,40*(query+max_chunk+256),0,.05,0,notes='10 units x2 enclosing x3 reranker;40 candidates, full query charged per candidate, measured2798-byte primary chunk max;256 framing tokens per pair.')
subtotal=sum(r['all_34_usd'] for r in rows);uncertainty=max(10,.25*subtotal);reservation=subtotal+uncertainty
out={'items':34,'release_sha256':hashlib.sha256(raw).hexdigest(),'single_turn':True,'max_question_utf8_bytes':longest,
 'compiled_request_bound_bytes':query,'family_serialized_bytes':family_bytes,'family_schema_framing_allowance':family_overhead,
 'rows':rows,'subtotal_usd':subtotal,'unpriced_allowance_usd':uncertainty,'reservation_usd':reservation,
 'proposed_spending_stop_usd':max(50,2*reservation),'expected_cost_range':'unavailable: new selector/review package has no representative live usage; older totals incomplete',
 'rates':'Retained OpenAI2026-08-19 input.25/output2; Voyage embedding2026-08-12 .12/rerank2026-09-04 .05 USD per million; not fresh prices; no cache discounts.',
 'unknowns':['25% or USD10 floor retains unpriced/local cost and pricing uncertainty, not zero.','Absent receipts charged at full reserved attempts.'],
 'unchanged_conservative_maxima':'10 search units retains4 ordinary+3 discovery+3 distinct-version alternatives; successful reuse and failed discovery may make maxima mutually exclusive, but none subtracted.',
 'authority':'proposal_only_no_spend_authorisation','settings_changed':False}
(HERE/'reservation.json').write_text(json.dumps(out,indent=2)+'\n')
print(json.dumps({k:out[k] for k in ('subtotal_usd','reservation_usd','proposed_spending_stop_usd','family_serialized_bytes')}))
