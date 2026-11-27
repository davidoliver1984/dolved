"""Frozen smoke command adapters. No authorisation is supplied by this file."""
import hashlib,json,os,subprocess,sys
from pathlib import Path

ROOT=Path(__file__).resolve().parent
WORKTREE=Path('/Users/davidoliver/Desktop/RAG-current-policy-pilot')
DEPLOY=ROOT
WORKER='dolved-r28-s03-smoke-03-worker'
def sha(p):return hashlib.sha256(Path(p).read_bytes()).hexdigest()
def inspect(name):return json.loads(subprocess.check_output(['docker','inspect',name]))[0]
def binding():return json.loads((ROOT/'binding.json').read_text())
def readiness():
    # Infrastructure-only preparation: the approved script denies networking and
    # loads cached files, never encoding a question or contacting a provider.
    b=binding();p=WORKTREE/'scripts/evaluation/current_policy_sparse_readiness.py'
    assert sha(p)==b['sparse_readiness']['script_sha256']
    result=subprocess.run(['docker','exec','-i','-e','HF_HUB_OFFLINE=1','-e','TRANSFORMERS_OFFLINE=1','dolved-r28-s03-ai-1','python','-'],input=p.read_text(),text=True,capture_output=True,check=True)
    value=json.loads(result.stdout)
    assert value['loaded'] and value['network_attempts']==[] and value['encoded_questions']==0
    assert value['cache_files']==b['sparse_readiness']['cache_files']
    return value
def future_worker():
    b=binding();cmd=['docker','compose','--project-directory',str(WORKTREE),'--env-file',str(WORKTREE/'tests/evaluation/runtime/.env.r28-s03'),'-p','dolved-r28-s03']
    for f in [WORKTREE/'compose.yaml',WORKTREE/'tests/end-to-end/runtime/compose.e2e.yaml',WORKTREE/'tests/evaluation/runtime/compose.r28-s03.yaml',Path.home()/'.dolved/r28-s03/production.override.json',ROOT/'smoke.override.json']:cmd+=['-f',str(f)]
    config=json.loads(subprocess.check_output(cmd+['config','--format','json'],cwd=WORKTREE,env={**os.environ,'R28_CORPUS_ROOT':'/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring'}))
    service=config['services']['api'];api=inspect('dolved-r28-s03-api-1')
    assert hashlib.sha256(json.dumps(service,sort_keys=True).encode()).hexdigest()==b['future_worker']['compose_service_sha256']
    assert service['image']==api['Image']==b['deployed_containers']['api']['image']
    env=dict(x.split('=',1) for x in api['Config']['Env'])
    assert all(env.get(k)==str(v) for k,v in service['environment'].items())
    sys.path.insert(0,str(WORKTREE/'scripts/evaluation/current_policy_confirmation'))
    from mount_guard import verify_api_mounts
    verify_api_mounts(config,api,b['future_worker'],mounted)

def php(file,payload):
    script=(WORKTREE/'scripts/evaluation/current_policy_confirmation'/file).read_text()
    # Read stdin for the unchanged helper payload, not for PHP source.
    return subprocess.run(['docker','exec','-e','CURRENT_POLICY_APP_ROOT=/app','-i','dolved-r28-s03-api-1','php','-r',script.removeprefix('<?php')],input=payload,text=True,capture_output=True,check=True).stdout
def mounted():
    b=binding()
    for service in ['api','ai','web']:
        c=inspect('dolved-r28-s03-'+service+'-1')
        expected=b['deployed_containers'][service]
        assert c['Id']==expected['id'] and c['Image']==expected['image'] and c['State']['Running']
        assert hashlib.sha256(json.dumps(c['Config'],sort_keys=True).encode()).hexdigest()==expected['configuration_sha256']
        rows={}
        for line in (DEPLOY/'runtime-source.sha256').read_text().splitlines():
            h,n=line.split('  ',1);prefix='apps/'+service+'/'
            path='/app/'+n[len(prefix):] if n.startswith(prefix) else '/'+n if service!='web' and n.startswith('contracts/') else None
            if path:rows[path]=h
        checks={
          'api':['php','-r','$m=json_decode(stream_get_contents(STDIN),true);foreach($m as $p=>$h){if(!is_file($p)||hash_file("sha256",$p)!==$h)exit(1);}'],
          'ai':['python','-c','import sys,json,hashlib,pathlib;m=json.load(sys.stdin);assert all(hashlib.sha256(pathlib.Path(p).read_bytes()).hexdigest()==h for p,h in m.items())'],
          'web':['node','-e','const fs=require("fs"),c=require("crypto");let s="";process.stdin.on("data",v=>s+=v);process.stdin.on("end",()=>{for(const [p,h] of Object.entries(JSON.parse(s)))if(c.createHash("sha256").update(fs.readFileSync(p)).digest("hex")!==h)process.exit(1)});']}
        subprocess.run(['docker','exec','-i',c['Id'],*checks[service]],input=json.dumps(rows),text=True,check=True)
def guard():
    mounted()
    names=subprocess.check_output(['docker','ps','--filter','label=com.docker.compose.project=dolved-r28-s03','--format','{{.Names}}'],text=True).splitlines()
    for n in names:
        c=inspect(n);command=' '.join(c['Config'].get('Cmd') or [])
        assert 'queue:work' not in command and 'schedule:' not in command and not any(x in n for x in ['worker','publisher','bootstrap'])
    allnames=subprocess.check_output(['docker','ps','-a','--format','{{.Names}}'],text=True).splitlines()
    assert WORKER not in allnames
    code='require "/app/vendor/autoload.php";$a=require "/app/bootstrap/app.php";$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();Illuminate\\Support\\Facades\\DB::beginTransaction();Illuminate\\Support\\Facades\\DB::statement("SET TRANSACTION READ ONLY");$n=Illuminate\\Support\\Facades\\DB::table("jobs")->where("queue","conversation-generation")->count();$q=config("queue.connections.conversation");echo json_encode(["count"=>$n,"queue"=>$q]);Illuminate\\Support\\Facades\\DB::rollBack();'
    q=json.loads(subprocess.check_output(['docker','exec','dolved-r28-s03-api-1','php','-r',code],text=True))
    assert q['count']==0 and q['queue']==binding()['queue_config'] and q['queue']['retry_after']==960
    future_worker()
    readiness()
def worker_start():
    guard()
    b=binding();auth=json.loads(Path(b['authorization']).read_text())
    assert auth['binding_sha256']==sha(ROOT/'binding.json') and auth['approved'] is True
    assert Path(b['authorization']+'.consumed').is_dir() and (ROOT/'started.json').is_file()
    cmd=['docker','compose','--project-directory',str(WORKTREE),'--env-file',str(WORKTREE/'tests/evaluation/runtime/.env.r28-s03'),'-p','dolved-r28-s03']
    for f in [WORKTREE/'compose.yaml',WORKTREE/'tests/end-to-end/runtime/compose.e2e.yaml',WORKTREE/'tests/evaluation/runtime/compose.r28-s03.yaml',Path.home()/'.dolved/r28-s03/production.override.json',ROOT/'smoke.override.json']:cmd+=['-f',str(f)]
    cmd+=['run','-d','--no-deps','--pull','never','--name',WORKER,'--label','dolved.smoke-binding='+sha(ROOT/'binding.json'),'api','php','artisan','queue:work','conversation','--queue=conversation-generation','--tries=1','--timeout=900']
    subprocess.run(cmd,cwd=WORKTREE,env={**os.environ,'R28_CORPUS_ROOT':'/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring'},check=True)
def worker_stop():
    names=subprocess.check_output(['docker','ps','-a','--format','{{.Names}}'],text=True).splitlines()
    if WORKER not in names:return
    c=inspect(WORKER)
    assert c['Config']['Labels'].get('dolved.smoke-binding')==sha(ROOT/'binding.json')
    subprocess.run(['docker','stop',WORKER],check=True)
    # Preserve this stopped labelled container for review; never restart it.
def main():
    mode=sys.argv[1]
    if mode=='mounted':mounted()
    elif mode=='guard':guard()
    elif mode=='readiness':print(json.dumps(readiness()))
    elif mode=='worker-start':worker_start()
    elif mode=='worker-stop':worker_stop()
    elif mode in ['preflight','capture']:print(php(mode+'.php',sys.stdin.read()))
    elif mode=='execute':
        # Phase B only. Credential stays in memory/environment, never evidence.
        sys.path.insert(0,str(WORKTREE/'scripts/evaluation/current_policy_confirmation'))
        import runner
        script='require "/app/vendor/autoload.php";$a=require "/app/bootstrap/app.php";$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$u=App\\Models\\User::where("public_id","ae454ce6-4d91-4c7e-9a40-b4750b696a6b")->firstOrFail();$p=getenv("E2E_ACCOUNT_PASSWORD");if(!$p||!Illuminate\\Support\\Facades\\Hash::check($p,$u->password))exit(2);echo $p;'
        p=subprocess.run(['docker','exec','dolved-r28-s03-api-1','php','-r',script],capture_output=True,text=True,check=True).stdout
        os.environ['CURRENT_POLICY_CONFIRMATION_PASSWORD']=p
        b=binding();b['binding_sha256']=sha(ROOT/'binding.json')
        try:runner.execute(b)
        finally:os.environ.pop('CURRENT_POLICY_CONFIRMATION_PASSWORD',None)
    else:raise ValueError('Unknown bounded command')
if __name__=='__main__':main()
