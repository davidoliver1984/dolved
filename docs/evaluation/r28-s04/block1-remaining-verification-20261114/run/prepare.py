"""Prepare checkpoint-only copies from committed capture; never load dirty runner."""
import hashlib, json, subprocess
from pathlib import Path
R=Path('/workspace'); P=Path(__file__).resolve().parent
HEAD='c28e69c15a1ae03f900fdb60ca579aafab8cb34f'
def git(*args): return subprocess.check_output(['git','-c','safe.directory=/workspace',*args],cwd=R).decode()
assert git('rev-parse','HEAD').strip()==HEAD
old='docs/evaluation/r28-s04/block1-c2-live-20261114'
for name in ['harness','inputs','cases','preflight','proof']: (P/name).mkdir(exist_ok=True)
for name in ['C1Capture.php','dispatch.php','preflight.php','verify_persistence.php','accounting.py','check_record.py','check_preflight.py','prepare_capture_test.py','test_stop_logic.py']:
    s=git('show',HEAD+':'+old+'/harness/'+name)
    s=s.replace(old,str(P.relative_to(R)))
    if name=='accounting.py':
        s=s.replace("'cap_usd':1.5", "'cap_usd':50.0").replace("1.5-known", "50.0-known").replace('<=1.5','<=50.0')
    if name=='C1Capture.php':
        s=s.replace("'error'=>$body['error']??null,", "'error'=>$body['error']??null,'planner_detail'=>str_ends_with($path=$this->calls[$key]['path'],'/plan-needs')?($body['detail']??null):null,")
        s=s.replace("'availability'=>[", "'availability'=>['rejected_planner_output'=>'unavailable','calendar_normalisation_count'=>'unavailable',")
    (P/'harness'/name).write_text(s)
source=R/'docs/evaluation/r28-s04/block1-extractive-checkpoint-20261113/execution-input.json'
assert hashlib.sha256(source.read_bytes()).hexdigest()=='ee4fb0903fc0feff93510266455a3c8a520938787d25b5c7027a680e402dd951'
items=json.loads(source.read_text())['items']
manifest=[]
for n,item in enumerate(items[16:32],17):
    row={'id':str(n),'utterance':item['utterance']}
    (P/'inputs'/f'{n}.json').write_text(json.dumps(row,ensure_ascii=False)+'\n')
    manifest.append({**row,'case_id':item['case_id'],'variant_id':item['variant_id'],'cohort':'remaining_new_runtime'})
source2=R/'docs/evaluation/r28-s04/block1-c2-live-20261114/released-input/questions-only.json'
assert hashlib.sha256(source2.read_bytes()).hexdigest()=='7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f'
d=json.loads(source2.read_text())
for item in d['fresh_items']+d['known_regression_items']:
    if item['id'] in ('G2','G3'):
        (P/'inputs'/f"{item['id']}.json").write_text(json.dumps(item,ensure_ascii=False)+'\n')
        manifest.append({**item,'cohort':'known_regression_ungraded'})
(P/'selection.json').write_text(json.dumps(manifest,indent=2)+'\n')
paths=git('ls-files','--','apps/api/app','apps/api/config','apps/api/routes','apps/ai/app','contracts').splitlines()
rows=[(hashlib.sha256((R/x).read_bytes()).hexdigest(),x) for x in sorted(paths)]
inventory=''.join(f'{h}  {x}\n' for h,x in rows)
assert hashlib.sha256(inventory.encode()).hexdigest()=='cbff39c23c64c7226ca3592248816884f2af8a5dcf7d5672f125d524078ba238'
(P/'preflight/runtime-source.sha256').write_text(inventory)
for service in ('api','ai'):
    (P/f'preflight/{service}-mounted.sha256').write_text(''.join(f'{h}  '+('/app/'+x[len('apps/'+service+'/'):] if x.startswith('apps/'+service+'/') else '/'+x)+'\n' for h,x in rows if x.startswith('apps/'+service+'/') or x.startswith('contracts/')))
print('Prepared 18 question-only inputs and committed capture copies; no providers.')
