"""Bind committed runtime, executable harness, three inputs and conservative ceiling."""
import hashlib,json,math,subprocess
from pathlib import Path
P=Path(__file__).resolve().parent; ROOT=Path('/workspace')
def git(*args): return subprocess.check_output(['git','-c','safe.directory=/workspace',*args],cwd=ROOT).decode().strip()
assert git('branch','--show-current')=='codex/block1-extractive-hold-20261112'
paths=git('ls-files','--','apps/api/app','apps/api/config','apps/api/routes','apps/ai/app','contracts').splitlines()
assert 'apps/ai/app/evaluation_capture.py' in paths
rows=[(hashlib.sha256((ROOT/p).read_bytes()).hexdigest(),p) for p in sorted(paths)]
inventory=''.join(f'{h}  {p}\n' for h,p in rows)
(P/'preflight/runtime-source.sha256').write_text(inventory)
for service in ('api','ai'):
    (P/f'preflight/{service}-mounted.sha256').write_text(''.join(f'{h}  '+('/app/'+p[len('apps/'+service+'/'):] if p.startswith('apps/'+service+'/') else '/'+p)+'\n' for h,p in rows if p.startswith('apps/'+service+'/') or p.startswith('contracts/')))
selection=json.loads((P/'selection.json').read_text())
assert [r['id'] for r in selection]==['G3','17','25']
q=max(len(r['utterance'].encode()) for r in selection)*9+8
assert q<8000
b=json.loads((P/'preflight/corpus-budget.json').read_text()); assert b['corpus_bounds']['max_text_bytes']==2798
stages=[('planner',4,101024,25000,.25,2),('interpretation',4,101024,4096,.25,2),
 ('family_review',6,101024,4096,.25,2),('generation',6,400000,4096,.25,2),
 ('entailment',6,400000,1024,.25,2),('embedding',80,q,0,.12,0),('reranking',60,40*(2798+512)+q,0,.05,0)]
costs=[{'stage':n,'calls_per_item':c,'input_allowance':i,'output_ceiling':o,'input_rate_per_million':a,'output_rate_per_million':z,'all_3_usd':3*c*(i*a+o*z)/1e6} for n,c,i,o,a,z in stages]
subtotal=sum(x['all_3_usd'] for x in costs); reserve=math.ceil(subtotal+5); ceiling=max(50,2*reserve)
budget={'cap_usd':ceiling,'reservation_usd':reserve,'stage_subtotal_usd':subtotal,'unpriced_uncertainty_allowance_usd':5,'stages':costs,
 'basis':'Same retained-rate, byte-as-token bounds and all production attempts as preceding 18-item run; no limits reduced. Rates are estimates, not invoice pricing.',
 'max_compiled_query_utf8_bytes':q,'fits':reserve<=ceiling}
(P/'reservation.json').write_text(json.dumps(budget,indent=2)+'\n')
files=list((P/'harness').glob('*'))+list((P/'inputs').glob('*.json'))+[P/n for n in ('prepare.py','freeze.py','budget.php','run_once.sh','capture.override.yaml','selection.json','reservation.json')]+list((P/'preflight').glob('*'))
manifest=''.join(f'{hashlib.sha256(x.read_bytes()).hexdigest()}  {x.relative_to(P)}\n' for x in sorted(files) if x.is_file())
(P/'governed-inputs.sha256').write_text(manifest)
auth={'authorization_id':'R28-TEMPORAL-ASSOCIATION-DIAGNOSTIC-AUTH-20261114-01','run_id':'R28-TEMPORAL-ASSOCIATION-DIAGNOSTIC-20261114-01',
 'classification':'DIAGNOSTIC_NEW_OBSERVATIONS','head':git('rev-parse','HEAD'),'main':git('rev-parse','main'),
 'runtime':hashlib.sha256(inventory.encode()).hexdigest(),'manifest_sha256':hashlib.sha256(manifest.encode()).hexdigest(),
 'items':['G3','17','25'],'one_use':True,'reservation_usd':reserve,'cap_usd':ceiling,
 'capture':'Serving AI evaluation-only sidecar, authorised workspace only, default-off; rejected parsed fields and actual calendar notation count.',
 'prior_verdicts':'Unchanged; this diagnostic does not replace earlier outcomes.'}
(P/'authorization.json').write_text(json.dumps(auth,indent=2)+'\n')
print(json.dumps(auth,indent=2))
