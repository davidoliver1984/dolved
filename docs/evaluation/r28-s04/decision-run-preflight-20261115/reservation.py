"""Conservative retained-rate envelope, NOT an authorisation or expected spend."""
import json
from pathlib import Path

HERE=Path(__file__).resolve().parent
rows=[]
def stage(name,requests,inputs,outputs,rate_in=.25,rate_out=2,notes=''):
    dollars=requests*(inputs*rate_in+outputs*rate_out)/1_000_000
    rows.append(dict(stage=name,requests_per_item=requests,input_tokens_per_request=inputs,
        output_tokens_per_request=outputs,usd_per_million_input=rate_in,
        usd_per_million_output=rate_out,per_item_usd=dollars,all_34_usd=dollars*34,notes=notes))

# Input allowance deliberately uses a whole 400k model context per LLM call,
# without subtracting output. Oversize requests reject; this is not a latency promise.
stage('planner',4,400000,25000,notes='2 Python plan attempts x up to 2 enclosing Laravel transport attempts; completed validation exhaustion is not retried.')
stage('interpretation_check',4,400000,4096,notes='At most one check per valid proposed plan; no separate temporal checker.')
stage('topical_family_review',6,400000,4096,notes='At most 3 needs x 2 Laravel transport attempts; optional C2 failures may terminate earlier.')
stage('coverage_selection',1,30000,4096,notes='One optional call; no retry; 6 aspects / 20 passages / 5 final passages.')
stage('generation',6,400000,4096,notes='2 validation attempts x 3 Python transport attempts; no SDK retries.')
stage('source_review_entailment',6,400000,17408,notes='Same sequential call, not two stages. Review transport retry may regenerate; conservative six of each, not additive aliases.')
# Broad lane envelope: four ordinary lanes, up to three discovery lanes and up
# to three distinct-version alternatives. Some maxima cannot co-occur. No limits changed.
searches=10*2
stage('query_embedding',searches*4,32000,0,.12,0,notes='10 conservative search units x 2 Laravel attempts x 4 embedding attempts; 8000 Unicode characters <=32000 UTF-8 bytes/tokens.')
max_chunk=json.loads((HERE/'verification/corpus-bounds.json').read_text())['corpus_bounds']['max_text_bytes']
stage('reranking',searches*3,40*(32000+max_chunk),0,.05,0,notes='40 candidates, query charged once per candidate conservatively, maximum ingested chunk UTF-8 bytes; 3 attempts per unit. Local sparse/Qdrant not another paid reranker.')
subtotal=sum(r['all_34_usd'] for r in rows)
uncertainty=max(10,subtotal*.25)
out={'items':34,'status':'proposal_only_no_authorisation','models':{'llm':'openai/gpt-5-mini','embedding':'voyage/voyage-4-large','reranker':'voyage/rerank-2.5','sparse':'local fastembed/Splade_PP_en_v1'},
 'rate_basis':'Retained application settings: OpenAI snapshot 2026-08-19 (.25 input / 2 output per million), Voyage embedding 2026-08-12 (.12), reranker 2026-09-04 (.05). Not a fresh price verification; no cache discounts.',
 'rows':rows,'subtotal_usd':subtotal,'unpriced_uncertainty_allowance_usd':uncertainty,
 'conservative_reservation_usd':subtotal+uncertainty,'proposed_ceiling_usd':max(50,2*(subtotal+uncertainty)),
 'unknowns':['Current billed prices may differ from retained rates; additive 25% or USD10 allowance, whichever larger.','Local compute/index/storage costs are not metered per item; not asserted zero; covered provisionally by uncertainty allowance.','Lost-response billing and unavailable provider usage are reserved as fully charged attempts.'],
 'conditions':['Revalidate unchanged limits, corpus and model identities before Phase B.','No contextualiser call for single-turn needs path. Intrinsic catalogue binding belongs to planner, source review and entailment are one call.','No input release read. This deliberately loose maximum is not an expected spend estimate.','Provider-free sizing does not guarantee completion inside existing 120-second provider / 480-second outer generation timeouts.']}
(HERE/'reservation.json').write_text(json.dumps(out,indent=2)+'\n')
print(json.dumps({k:out[k] for k in ('subtotal_usd','conservative_reservation_usd','proposed_ceiling_usd')}))
