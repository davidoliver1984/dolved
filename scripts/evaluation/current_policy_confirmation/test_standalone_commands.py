"""Copied adapter, fresh OS process per mode; no live Docker/provider boundary."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

SOURCE=Path(__file__).resolve().parent
SECRET='SYNTHETIC_DO_NOT_DISCLOSE'

class StandaloneCommandsTests(unittest.TestCase):
    def fixture(self,t,mode,scenario):
        root=Path(t);work=root/'work';src=work/'scripts/evaluation/current_policy_confirmation';src.mkdir(parents=True)
        prep=root/'prep';prep.mkdir();cwd=root/'unrelated';cwd.mkdir();binpath=root/'bin';binpath.mkdir();out=root/'execution';out.mkdir()
        (cwd/'runner.py').write_text('raise RuntimeError("'+SECRET+'")')
        text=(SOURCE/'observer_commands.py').read_text().replace("Path('/Users/davidoliver/Desktop/RAG-current-policy-pilot')",'Path('+repr(str(work))+')')
        (prep/'commands.py').write_text(text)
        tail="\ndef execute(binding):\n    durable(Path(binding['destination'])/'execution-branch.json', {'reached':True}, True)\n    if binding.get('scenario')=='execution_error': raise ValueError('SYNTHETIC_DO_NOT_DISCLOSE')\n"
        (src/'runner.py').write_text((SOURCE/'runner.py').read_text()+tail)
        for name in ['rehearsal_runner.py','rehearsal_compose.py','native_capture.py']:shutil.copyfile(SOURCE/name,src/name)
        shutil.copyfile(prep/'commands.py',src/'observer_commands.py')
        for name in ['preflight.php','capture.php']:(src/name).write_text('<?php /* controlled '+name+' */')
        sparse=work/'scripts/evaluation/current_policy_sparse_readiness.py';sparse.write_text('# controlled')
        (prep/'observer.json').write_text(json.dumps({'source_files':{n:'controlled' for n in ['observed_worker.php','worker_observer.php']}}));(prep/'runtime-source.sha256').write_text('')
        base={'image':'image','environment':{'A':'a=b'},'command':['api'],'volumes':[]};command=['php','/evaluation-observer/observed_worker.php','/evaluation-observer-config/observer.json'];observed={**base,'command':command,'ports':[],'depends_on':{}}
        api={'Id':'api-id','Image':'image','State':{'Running':True},'Mounts':[],'Config':{'Env':['A=a=b'],'Cmd':['api']}}
        worker={'Id':'worker-id','Image':'image','State':{'Running':mode=='worker-stop','Status':'running' if mode=='worker-stop' else 'created','StartedAt':'0001-01-01T00:00:00Z'},'Mounts':[],'Config':{'Env':['A=a=b'],'Cmd':command,'Entrypoint':['entry'],'Labels':{'owner':'wrong' if scenario=='wrong_owner' else 'owned'}}}
        digest=lambda v:hashlib.sha256(json.dumps(v,sort_keys=True).encode()).hexdigest()
        b={'destination':str(out),'scenario':scenario,'authorization':str(root/'auth.json'),'queue_config':{},'deployed_containers':{s:{'id':s+'-id','image':'image','configuration_sha256':digest(api['Config'])} for s in ['api','ai','web']},'future_worker':{'compose_service_sha256':digest(base)},'observer_worker':{'name':'owned-worker','service':'observer-question','command':command,'source_files':{},'mounts':{},'mount_spec':{},'mounted_files':{},'compose_service_sha256':digest(observed),'image':'image','entrypoint':['entry'],'environment_sha256':digest({'A':'a=b'}),'ownership_label':'owner','owner_id':'owned'},'sparse_readiness':{'script_sha256':hashlib.sha256(sparse.read_bytes()).hexdigest(),'cache_files':{}}}
        # Controlled mount boundary only; actual shared-validator tests run separately.
        (src/'mount_guard.py').write_text('def verify_api_mounts(c,a,b,check): check()\ndef build_mount_spec(*a): return {}\ndef mount_mismatches(*a): return []\ndef verify_mounts(*a): pass\n')
        (prep/'binding.json').write_text(json.dumps(b));(root/'auth.json').write_text(json.dumps({'binding_sha256':hashlib.sha256((prep/'binding.json').read_bytes()).hexdigest(),'approved':True}));(root/'auth.json.consumed').mkdir();(out/'started.json').write_text('{}');(out/'worker-timing.json').write_text('{"deadline_epoch":9999999999}')
        if mode=='worker-stop':(out/'item-result.json').write_text('{"outcome":"completed"}')
        if scenario=='prior_error':(out/'primary-halt.json').write_text('{"code":"controlled_primary"}')
        state={'scenario':scenario,'worker_present':mode=='worker-stop' and scenario!='absent','worker':worker,'api':api,'config':{'services':{'api':base,'observer-question':observed},'volumes':{}}};statepath=root/'state.json';statepath.write_text(json.dumps(state));calls=root/'calls.jsonl'
        fake='''#!INTERPRETER
import json,sys,pathlib
p=pathlib.Path(STATE);s=json.loads(p.read_text());a=sys.argv[1:]
with pathlib.Path(CALLS).open('a') as f:f.write(json.dumps(a)+'\\n')
if a[0]=='inspect':
 n=a[1];v=s['worker'] if n in ['owned-worker','worker-id'] else dict(s['api'],Id=n.replace('dolved-r28-s03-','').replace('-1','')+'-id');print(json.dumps([v]))
elif a[0]=='ps':print('owned-worker' if s['worker_present'] else '')
elif a[0]=='compose':
 if 'config' in a:print(json.dumps(s['config']))
 elif 'create' in a:s['worker_present']=True
elif a[0]=='start':s['worker']['State'].update(Running=True,Status='running',StartedAt='2026-11-18T08:00:00Z')
elif a[0]=='stop':
 if s['scenario']=='cleanup_error':print('SYNTHETIC_DO_NOT_DISCLOSE',file=sys.stderr);sys.exit(9)
 s['worker']['State'].update(Running=False,Status='exited',ExitCode=0)
elif a[0]=='exec':
 if 'python' in a:print(json.dumps({'loaded':True,'network_attempts':[],'encoded_questions':0,'cache_files':{}}))
 elif 'selectRaw' in a[-1]:print(json.dumps({'queues':[{'queue':'document-governance','count':17}],'config':{}}))
 elif 'echo $p' in a[-1]:print('SYNTHETIC_DO_NOT_DISCLOSE')
 elif '-r' in a and 'controlled' in a[-1]:print(json.dumps({'branch':'capture' if 'capture.php' in a[-1] else 'preflight'}))
p.write_text(json.dumps(s))
'''.replace('INTERPRETER',sys.executable).replace('STATE',repr(str(statepath))).replace('CALLS',repr(str(calls)))
        (binpath/'docker').write_text(fake);(binpath/'docker').chmod(0o700)
        return prep,cwd,out,{'PATH':str(binpath),'HOME':str(root),'PYTHONDONTWRITEBYTECODE':'1'},calls

    def invoke(self,mode,scenario='ok'):
        with tempfile.TemporaryDirectory() as t:
            prep,cwd,out,env,calls=self.fixture(t,mode,scenario)
            if scenario=='missing_binding':(prep/'binding.json').unlink()
            if scenario=='malformed_binding':(prep/'binding.json').write_text('{')
            p=subprocess.run([sys.executable,str(prep/'commands.py'),mode],cwd=cwd,env=env,input='{}',text=True,capture_output=True)
            evidence={x.name:x.read_text() for x in out.iterdir() if x.is_file()}
            self.assertNotIn(SECRET,p.stdout+p.stderr+json.dumps(evidence));self.assertNotIn('ModuleNotFoundError',p.stderr)
            return p,evidence,calls.read_text() if calls.exists() else ''

    def test_every_mode_is_standalone(self):
        for mode in ['mounted','guard','readiness','preflight','capture','worker-start','worker-stop','execute']:
            with self.subTest(mode=mode):
                p,e,calls=self.invoke(mode);self.assertEqual(p.returncode,0,p.stderr);self.assertTrue(calls)
                if mode=='worker-start':self.assertIn('worker-post-start.json',e);self.assertIn('["start", "worker-id"]',calls)
                if mode=='worker-stop':self.assertIn('worker-exit.json',e);self.assertIn('item-result.json',e)
                if mode=='execute':self.assertIn('execution-branch.json',e)
                if mode in ['preflight','capture']:self.assertEqual(json.loads(p.stdout)['branch'],mode)

    def test_cleanup_and_execution_failures(self):
        for scenario,code in [('prior_error',0),('cleanup_error',1),('absent',0),('wrong_owner',1),('missing_binding',1),('malformed_binding',1)]:
            with self.subTest(scenario=scenario):
                p,e,calls=self.invoke('worker-stop',scenario);self.assertEqual(p.returncode,code)
                if scenario=='prior_error':self.assertIn('primary-halt.json',e);self.assertIn('item-result.json',e)
                if scenario in ['wrong_owner','absent']:self.assertNotIn('["stop"',calls)
        p,e,_=self.invoke('execute','execution_error');self.assertEqual(p.returncode,1);self.assertIn('execution-branch.json',e)

    def test_host_awake_separate_process(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);dest=root/'marker';exe=root/'bound-python';exe.write_text('#!'+sys.executable+'\nimport pathlib,sys\npathlib.Path('+repr(str(dest))+').write_text(sys.argv[-1])\n');exe.chmod(0o700)
            adapter=root/'commands.py';adapter.write_text('# controlled');sha=lambda p:hashlib.sha256(Path(p).read_bytes()).hexdigest()
            b={'binding_status':'EXECUTION_RELEASE','host_awake':{'command':[str(exe),str(adapter),'execute'],'files':{str(p):sha(p) for p in ['/usr/bin/caffeinate',exe,adapter]}}};path=root/'binding.json';path.write_text(json.dumps(b))
            p=subprocess.run([sys.executable,str(SOURCE/'host_awake.py'),str(path)],cwd=root,env={'PATH':'/usr/bin:/bin','HOME':t},capture_output=True,text=True)
            self.assertEqual(p.returncode,0,p.stderr);self.assertEqual(dest.read_text(),'execute')

if __name__=='__main__':unittest.main()
