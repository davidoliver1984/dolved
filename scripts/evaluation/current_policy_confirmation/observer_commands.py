"""Frozen one-item observer integration adapter. Copy to its bound preparation root.

No operational authority supplied here. The original smoke adapter remains frozen.
"""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parent
WORKTREE = Path('/Users/davidoliver/Desktop/RAG-current-policy-pilot')
WORKER = 'dolved-r28-s03-observer-integration-01-worker'


def sha(p): return hashlib.sha256(Path(p).read_bytes()).hexdigest()
def inspect(name): return json.loads(subprocess.check_output(['docker','inspect',name]))[0]
def binding(): return json.loads((ROOT/'binding.json').read_text())
def environment_map(entries):
    result = {}
    if not isinstance(entries, list): raise ValueError('environment_malformed')
    for entry in entries:
        if not isinstance(entry, str) or '=' not in entry:
            raise ValueError('environment_malformed')
        name, value = entry.split('=', 1)
        if not name or '\x00' in entry: raise ValueError('environment_malformed')
        if name in result: raise ValueError('environment_duplicate_name')
        result[name] = value
    return result


def verify_environment(expected, actual):
    if environment_map(expected) != environment_map(actual):
        raise ValueError('environment_mapping_mismatch')

def env(): return {**os.environ,'R28_CORPUS_ROOT':'/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring'}


def compose(observer=False):
    cmd=['docker','compose','--env-file','/Users/davidoliver/Desktop/RAG/.env.r28-s03','-p','dolved-r28-s03']
    for f in ['compose.yaml','compose.e2e.yaml','compose.r28-s03.yaml',str(Path.home()/'.dolved/r28-s03/production.override.json'),str(ROOT/'smoke.override.json')]:cmd+=['-f',f]
    if observer:cmd+=['-f',str(ROOT/'observer-worker.override.json')]
    return cmd


def php(file,payload):
    script=(WORKTREE/'scripts/evaluation/current_policy_confirmation'/file).read_text()
    return subprocess.run(['docker','exec','-e','CURRENT_POLICY_APP_ROOT=/app','-i','dolved-r28-s03-api-1','php','-r',script.removeprefix('<?php')],input=payload,text=True,capture_output=True,check=True).stdout


def mounted():
    b=binding()
    for service in ['api','ai','web']:
        c=inspect('dolved-r28-s03-'+service+'-1');expected=b['deployed_containers'][service]
        assert c['Id']==expected['id'] and c['Image']==expected['image'] and c['State']['Running']
        assert hashlib.sha256(json.dumps(c['Config'],sort_keys=True).encode()).hexdigest()==expected['configuration_sha256']
        rows={}
        for line in (ROOT/'runtime-source.sha256').read_text().splitlines():
            h,n=line.split('  ',1);prefix='apps/'+service+'/'
            path='/app/'+n[len(prefix):] if n.startswith(prefix) else '/'+n if service!='web' and n.startswith('contracts/') else None
            if path:rows[path]=h
        checks={
          'api':['php','-r','$m=json_decode(stream_get_contents(STDIN),true);foreach($m as $p=>$h){if(!is_file($p)||hash_file("sha256",$p)!==$h)exit(1);}'],
          'ai':['python','-c','import sys,json,hashlib,pathlib;m=json.load(sys.stdin);assert all(hashlib.sha256(pathlib.Path(p).read_bytes()).hexdigest()==h for p,h in m.items())'],
          'web':['node','-e','const fs=require("fs"),c=require("crypto");let s="";process.stdin.on("data",v=>s+=v);process.stdin.on("end",()=>{for(const [p,h] of Object.entries(JSON.parse(s)))if(c.createHash("sha256").update(fs.readFileSync(p)).digest("hex")!==h)process.exit(1)});']}
        subprocess.run(['docker','exec','-i',c['Id'],*checks[service]],input=json.dumps(rows),text=True,check=True)


def readiness():
    b=binding();p=WORKTREE/'scripts/evaluation/current_policy_sparse_readiness.py'
    assert sha(p)==b['sparse_readiness']['script_sha256']
    result=subprocess.run(['docker','exec','-i','-e','HF_HUB_OFFLINE=1','-e','TRANSFORMERS_OFFLINE=1','dolved-r28-s03-ai-1','python','-'],input=p.read_text(),text=True,capture_output=True,check=True)
    v=json.loads(result.stdout)
    assert v['loaded'] and v['network_attempts']==[] and v['encoded_questions']==0 and v['cache_files']==b['sparse_readiness']['cache_files']
    return v


def validate_observer_service(base, observed, expected_mounts):
    expected_command=['php','/evaluation-observer/observed_worker.php','/evaluation-observer-config/observer.json']
    assert observed['command']==expected_command
    assert {k:v for k,v in observed.items() if k not in {'command','volumes'}}=={k:v for k,v in base.items() if k not in {'command','volumes'}}
    volumes={v['target']:v for v in observed['volumes']}
    original={v['target']:v for v in base['volumes']}
    assert len(volumes)==len(observed['volumes']) and set(volumes)==set(original)|set(expected_mounts)
    assert all(volumes[k]==v for k,v in original.items())
    for target,expected in expected_mounts.items():
        actual=volumes[target]
        assert actual['type']=='bind' and actual['source']==expected['source'] and actual.get('read_only',False)==expected['read_only']
    assert set(expected_mounts)=={'/evaluation-observer','/evaluation-observer-config/observer.json','/observer-journal','/observer-health'}
    assert expected_mounts['/evaluation-observer']['read_only'] and expected_mounts['/evaluation-observer-config/observer.json']['read_only']
    assert not expected_mounts['/observer-journal']['read_only'] and not expected_mounts['/observer-health']['read_only']


def future_worker():
    b=binding();config=json.loads(subprocess.check_output(compose()+['config','--format','json'],cwd=WORKTREE,env=env()))
    service=config['services']['api'];api=inspect('dolved-r28-s03-api-1')
    assert hashlib.sha256(json.dumps(service,sort_keys=True).encode()).hexdigest()==b['future_worker']['compose_service_sha256']
    assert service['image']==api['Image']==b['deployed_containers']['api']['image']
    actual_env=environment_map(api['Config']['Env'])
    assert all(actual_env.get(k)==str(v) for k,v in service['environment'].items())
    sys.path.insert(0,str(WORKTREE/'scripts/evaluation/current_policy_confirmation'))
    from mount_guard import verify_api_mounts
    verify_api_mounts(config,api,b['future_worker'],mounted)
    observed=json.loads(subprocess.check_output(compose(True)+['config','--format','json'],cwd=WORKTREE,env=env()))['services']['api']
    validate_observer_service(service,observed,b['observer_worker']['mounts'])
    assert hashlib.sha256(json.dumps(observed,sort_keys=True).encode()).hexdigest()==b['observer_worker']['compose_service_sha256']
    for path,digest in b['observer_worker']['source_files'].items():assert sha(path)==digest


def guard():
    mounted()
    names=subprocess.check_output(['docker','ps','--filter','label=com.docker.compose.project=dolved-r28-s03','--format','{{.Names}}'],text=True).splitlines()
    for name in names:
        c=inspect(name);command=' '.join(c['Config'].get('Cmd') or [])
        assert 'queue:work' not in command and 'observed_worker.php' not in command and 'schedule:' not in command and not any(x in name for x in ['worker','publisher','bootstrap'])
    assert WORKER not in subprocess.check_output(['docker','ps','-a','--format','{{.Names}}'],text=True).splitlines()
    code='require "/app/vendor/autoload.php";$a=require "/app/bootstrap/app.php";$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();Illuminate\\Support\\Facades\\DB::beginTransaction();Illuminate\\Support\\Facades\\DB::statement("SET TRANSACTION READ ONLY");echo json_encode(["queues"=>Illuminate\\Support\\Facades\\DB::table("jobs")->select("queue")->selectRaw("count(*) as count")->groupBy("queue")->get(),"config"=>config("queue.connections.conversation")]);Illuminate\\Support\\Facades\\DB::rollBack();'
    q=json.loads(subprocess.check_output(['docker','exec','dolved-r28-s03-api-1','php','-r',code],text=True))
    assert q['queues']==[{'queue':'document-governance','count':17}] and q['config']==binding()['queue_config']
    future_worker();readiness()


def worker_start():
    guard();b=binding();auth=json.loads(Path(b['authorization']).read_text())
    assert auth['binding_sha256']==sha(ROOT/'binding.json') and auth['approved'] is True
    assert Path(b['authorization']+'.consumed').is_dir() and (Path(b['destination'])/'started.json').is_file()
    cmd=compose(True)+['run','-d','--no-deps','--pull','never','--name',WORKER,'--label','dolved.observer-binding='+sha(ROOT/'binding.json'),'api','php','/evaluation-observer/observed_worker.php','/evaluation-observer-config/observer.json']
    subprocess.run(cmd,cwd=WORKTREE,env=env(),check=True)
    # Queue was empty at the guard; no item can be reserved/submitted until this
    # verification and the subsequent journal startup check both return.
    c=inspect(WORKER);api=inspect('dolved-r28-s03-api-1')
    assert c['Image']==api['Image']
    verify_environment(api['Config']['Env'],c['Config']['Env'])
    assert c['Config']['Cmd']==['php','/evaluation-observer/observed_worker.php','/evaluation-observer-config/observer.json']
    verify_started_mounts(api['Mounts'],c['Mounts'],b['observer_worker']['mounts'])


def verify_started_mounts(api_mounts, worker_mounts, extra):
    base={m['Destination']:m for m in api_mounts};actual={m['Destination']:m for m in worker_mounts}
    assert len(actual)==len(worker_mounts) and set(actual)==set(base)|set(extra)
    for target,expected in base.items():
        assert all(actual[target].get(k)==expected.get(k) for k in ['Type','Source','Destination','RW','Name'])
    for target,expected in extra.items():
        m=actual[target]
        assert m['Type']=='bind' and m['RW']==(not expected['read_only'])
        # Explicit per-entry spellings in the reviewed binding; no prefix strip.
        assert m['Source'] in expected['allowed_source_spellings']


def worker_stop():
    if WORKER not in subprocess.check_output(['docker','ps','-a','--format','{{.Names}}'],text=True).splitlines():return
    c=inspect(WORKER);assert c['Config']['Labels'].get('dolved.observer-binding')==sha(ROOT/'binding.json')
    subprocess.run(['docker','stop',WORKER],check=True)
    assert not inspect(WORKER)['State']['Running']


def main():
    mode=sys.argv[1]
    if mode=='mounted':mounted()
    elif mode=='guard':guard()
    elif mode=='readiness':print(json.dumps(readiness()))
    elif mode=='worker-start':worker_start()
    elif mode=='worker-stop':worker_stop()
    elif mode in ['preflight','capture']:print(php(mode+'.php',sys.stdin.read()))
    elif mode=='execute':
        sys.path.insert(0,str(WORKTREE/'scripts/evaluation/current_policy_confirmation'));import runner
        script='require "/app/vendor/autoload.php";$a=require "/app/bootstrap/app.php";$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$u=App\\Models\\User::where("public_id","ae454ce6-4d91-4c7e-9a40-b4750b696a6b")->firstOrFail();$p=getenv("E2E_ACCOUNT_PASSWORD");if(!$p||!Illuminate\\Support\\Facades\\Hash::check($p,$u->password))exit(2);echo $p;'
        password=subprocess.run(['docker','exec','dolved-r28-s03-api-1','php','-r',script],capture_output=True,text=True,check=True).stdout
        os.environ['CURRENT_POLICY_CONFIRMATION_PASSWORD']=password;b=binding();b['binding_sha256']=sha(ROOT/'binding.json')
        try:runner.execute(b)
        finally:os.environ.pop('CURRENT_POLICY_CONFIRMATION_PASSWORD',None)
    else:raise ValueError('Unknown bounded command')


if __name__=='__main__':main()
