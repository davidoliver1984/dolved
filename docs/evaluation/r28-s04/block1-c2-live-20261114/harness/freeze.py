"""Freeze only authorised runtime paths and this question-only harness; no providers."""
import hashlib,json,subprocess
from pathlib import Path
root=Path('/workspace'); p=root/'docs/evaluation/r28-s04/block1-c2-live-20261114'
def git(*args): return subprocess.check_output(['git','-c','safe.directory=/workspace',*args],cwd=root).decode().strip()
assert git('rev-parse','HEAD')=='829bbf132429ab8d4b408e4df2654e1b76fd2070'
assert git('branch','--show-current')=='codex/block1-extractive-hold-20261112'
paths=git('ls-files','--','apps/api/app','apps/api/config','apps/api/routes','apps/ai/app','contracts').splitlines()
rows=[(hashlib.sha256((root/x).read_bytes()).hexdigest(),x) for x in sorted(paths)]
inventory=''.join(f'{h}  {x}\n' for h,x in rows)
aggregate=hashlib.sha256(inventory.encode()).hexdigest()
assert aggregate=='7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af'
(p/'preflight/runtime-source.sha256').write_text(inventory)
for service in ('api','ai'):
    mounted=''.join(f'{h}  '+('/app/'+x[len('apps/'+service+'/'):] if x.startswith('apps/'+service+'/') else '/'+x)+'\n' for h,x in rows if x.startswith('apps/'+service+'/') or x.startswith('contracts/'))
    (p/f'preflight/{service}-mounted.sha256').write_text(mounted)
original=p/'released-input/questions-only.json'
assert hashlib.sha256(original.read_bytes()).hexdigest()=='7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f'
auth={'authorization_id':'R28-C2-AUTH-20261114-01','run_id':'R28-C2-LIVE-20261114-01','head':git('rev-parse','HEAD'),'implementation':'84f22f862e5001be998e6a8fe027f56a17db6941','runtime_aggregate':aggregate,'items':['G1','G2','G3','G4','G5','R16'],'cap_usd':1.5,'uncertainty_reserve_usd':.5,'minimum_next_item_headroom_usd':.5,'initial_conservative_reservation_usd':1.0,'once_only':True,'grading_authorised':False,'main':git('rev-parse','main'),'block_1':'HOLD'}
(p/'authorization.json').write_text(json.dumps(auth,indent=2)+'\n')
files=list((p/'harness').glob('*'))+list((p/'inputs').glob('*.json'))+[original,p/'authorization.json',p/'preflight/deployed.json',p/'preflight/runtime-source.sha256',p/'preflight/api-mounted.sha256',p/'preflight/ai-mounted.sha256']
frozen=''.join(f'{hashlib.sha256(x.read_bytes()).hexdigest()}  {x.relative_to(p)}\n' for x in sorted(files) if x.is_file())
with (p/'governed-inputs.sha256').open('x') as f: f.write(frozen)
print(json.dumps({'runtime_aggregate':aggregate,'governed_manifest_sha256':hashlib.sha256(frozen.encode()).hexdigest(),'head':auth['head']}))
