import hashlib,json,subprocess
from pathlib import Path
p=Path(__file__).resolve().parent.parent; root=Path('/workspace')
source=root/'docs/evaluation/r28-s04/block1-c2-live-20261114/released-input/questions-only.json'
assert hashlib.sha256(source.read_bytes()).hexdigest()=='7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f'
for d in ('inputs','requests','cases','preflight'): (p/d).mkdir(mode=0o700,exist_ok=True)
for item in json.loads(source.read_text())['fresh_items']:
    if item['id'] in ('G2','G3'):
        with (p/'inputs'/f"{item['id']}.json").open('x') as f: f.write(json.dumps(item,ensure_ascii=False)+'\n')
inventory=root/'docs/evaluation/r28-s04/block1-c2-live-20261114/preflight/runtime-source.sha256'
assert hashlib.sha256(inventory.read_bytes()).hexdigest()=='7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af'
(p/'runtime-source.sha256').write_bytes(inventory.read_bytes())
auth={'run_id':'R28-PLANNER-CONTRACT-DIAGNOSTIC-20261114-01','kind':'DIAGNOSTIC_ONLY','head':'13b7e09bb9cad2b5c6cb0aff600efeb8b217cdf4','runtime_aggregate':hashlib.sha256(inventory.read_bytes()).hexdigest(),'workspace':'5323cd6b-8b7a-40f9-98b0-9413c75f643e','items':['G2','G3'],'maximum_usd':10,'reservation_usd':3.25,'reservation_basis':{'planner_attempts':4,'planner_output_ceiling':25000,'interpretation_checks':4,'checker_output_ceiling':4096,'input_bytes_guard_per_call':1000000,'input_token_overhead_allowance_per_call':1024,'input_reserve_usd':2.002048,'output_reserve_usd':.232768,'uncertainty_usd':1,'rounded_reservation_usd':3.25},'retained_rates_usd_per_million':{'input':.25,'output':2},'no_downstream_execution':True,'one_use':True}
(p/'authorization.json').write_text(json.dumps(auth,indent=2)+'\n')
files=sorted([x for x in (p/'harness').glob('*') if x.is_file()]+list((p/'inputs').glob('*.json'))+[p/'authorization.json',p/'runtime-source.sha256',p/'proof/capture.xml'])
text=''.join(f'{hashlib.sha256(x.read_bytes()).hexdigest()}  {x.relative_to(p)}\n' for x in files)
with (p/'governed-inputs.sha256').open('x') as f:f.write(text)
print(json.dumps({'governed_sha256':hashlib.sha256(text.encode()).hexdigest(),'reservation_usd':3.25,'cap_usd':10}))
