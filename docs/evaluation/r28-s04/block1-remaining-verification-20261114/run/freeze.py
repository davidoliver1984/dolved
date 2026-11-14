import hashlib,json,math
from pathlib import Path
P=Path(__file__).resolve().parent
rows=json.loads((P/'selection.json').read_text())
q=max(len(r['utterance'].encode()) for r in rows)*9+8
assert q<8000
b=json.loads((P/'preflight/corpus-budget.json').read_text())
assert b['corpus_bounds']['max_text_bytes']==2798
# Byte-as-token allowances, uncached retained rates, all permitted retries.
stages=[
 ('planner',4,101024,25000,.25,2),
 ('interpretation',4,101024,4096,.25,2),
 ('family_review',6,101024,4096,.25,2),
 ('generation',6,400000,4096,.25,2),
 ('entailment',6,400000,1024,.25,2),
 ('embedding',80,q,0,.12,0),
 ('reranking',60,40*(2798+512)+q,0,.05,0)]
costs=[{'stage':n,'calls_per_item':c,'input_allowance':i,'output_ceiling':o,'input_rate_per_million':a,'output_rate_per_million':z,'all_18_usd':18*c*(i*a+o*z)/1e6} for n,c,i,o,a,z in stages]
subtotal=sum(x['all_18_usd'] for x in costs)
reserve=math.ceil(subtotal+5)
budget={'cap_usd':50,'reservation_usd':reserve,'stage_subtotal_usd':subtotal,'unpriced_uncertainty_allowance_usd':5,'stages':costs,'max_compiled_query_utf8_bytes':q,
 'basis':['Retained application rates, not invoice or new price quotation.','Planner request guard 100000 bytes plus 1024 framing. Interpretation/family 101024 allowance exceeds fixed single-turn grounded payload and bounded candidate snippets.','Generation and entailment reserve full 400000 input tokens plus output (more conservative than subtracting output). Two PHP validation requests times three provider attempts; SDK retries zero.','Four ordinary side lanes plus three discovery lanes plus three temporal alternatives = ten; double outer retrieval attempts. Four embedding attempts and three reranker attempts per side. Lane count already counts sides; no extra factor two.','Three family reviews doubled for outer retries. Planner/checker doubled outer envelope even though validation failures suppress it.','Reranker corpus maximum text bytes 2798 plus 512 metadata allowance per candidate, forty candidates per side; byte counts used as conservative token allowance.','No production limits, thresholds or retry policy modified.'], 'fits':reserve<=50}
(P/'reservation.json').write_text(json.dumps(budget,indent=2)+'\n')
assert reserve<=50, budget
files=[x for x in (P/'harness').glob('*') if x.is_file()]+list((P/'inputs').glob('*.json'))+[P/'prepare.py',P/'freeze.py',P/'budget.php',P/'run_once.sh',P/'selection.json',P/'reservation.json']+list((P/'preflight').glob('*'))
inventory=''.join(f'{hashlib.sha256(x.read_bytes()).hexdigest()}  {x.relative_to(P)}\n' for x in sorted(files) if x.is_file())
(P/'governed-inputs.sha256').write_text(inventory)
auth={'authorization_id':'R28-REMAINING-AUTH-20261114-01','run_id':'R28-BLOCK1-REMAINING-20261114-01','head':'c28e69c15a1ae03f900fdb60ca579aafab8cb34f','runtime':'cbff39c23c64c7226ca3592248816884f2af8a5dcf7d5672f125d524078ba238','harness_and_inputs_manifest_sha256':hashlib.sha256(inventory.encode()).hexdigest(),'items':[r['id'] for r in rows],'one_use':True,'cap_usd':50,'reservation_usd':reserve,'grading':'after execution only; G2/G3 ungraded','unavailable':['rejected_planner_output','calendar_normalisation_count']}
(P/'authorization.json').write_text(json.dumps(auth,indent=2)+'\n')
print(json.dumps({'reservation_usd':reserve,'subtotal_usd':subtotal,'max_query_bytes':q,'manifest_sha256':auth['harness_and_inputs_manifest_sha256']}))
