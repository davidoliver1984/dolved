import copy
import json
from pathlib import Path
import tempfile
import time
import unittest
from unittest.mock import patch
import observer_commands as commands
from test_shared_worker_identity import SharedWorkerIdentityTests


class NormalObserverWiringTests(unittest.TestCase):
    def test_normal_create_inspect_start_exact_id_post_content(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);(root/'binding.json').write_text('{}')
            spec,c=SharedWorkerIdentityTests().fixture()
            spec['command']=['php','/evaluation-observer/observed_worker.php','/evaluation-observer-config/observer.json'];spec['ownership_label']='dolved.observer-owner';spec['service']='observer-question'
            c['Config']['Cmd']=spec['command'];c['Config']['Labels']={'dolved.observer-owner':spec['owner_id']}
            live=copy.deepcopy(c);live['State'].update(Status='running',Running=True,StartedAt='2026-11-18T08:00:00Z')
            auth=root/'authority.json';auth.write_text(json.dumps({'binding_sha256':commands.sha(root/'binding.json'),'approved':True}));Path(str(auth)+'.consumed').mkdir();(root/'started.json').write_text('{}');(root/'worker-timing.json').write_text(json.dumps({'deadline_epoch':time.time()+300}))
            b={'authorization':str(auth),'destination':t,'observer_worker':spec};seen=[]
            def run(argv,**kwargs):seen.append(argv)
            with patch.object(commands,'ROOT',root),patch.object(commands,'guard'),patch.object(commands,'binding',return_value=b),patch.object(commands,'compose',return_value=['compose']),patch.object(commands,'inspect',side_effect=[c,live]),patch.object(commands.subprocess,'run',side_effect=run),patch('rehearsal_runner.content_check') as content:
                commands.worker_start()
            self.assertEqual(seen[0][1:4],['create','--no-recreate','--no-build'])
            self.assertEqual(seen[1],['docker','start','exact-id']);content.assert_called_once_with(spec,'exact-id')
            self.assertEqual(json.loads((root/'worker-post-start.json').read_text())['mismatch_codes'],[])
            self.assertNotIn('rehearsal_worker.php',' '.join(spec['command']))

    def test_mixed_approved_sources_in_one_container(self):
        spec,c=SharedWorkerIdentityTests().fixture()
        from mount_guard import verify_mounts
        c['Mounts'][0]['Source']='/host_mnt'+c['Mounts'][0]['Source']
        verify_mounts(spec['mount_spec'],c['Mounts'])

    def test_readiness_deadline_uses_actual_start_without_reset(self):
        import runner
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);p=root/'manifest.json';p.write_text(json.dumps({'items':[],'evaluated_at_assumption':'fixed'}))
            b={'provider_free':True,'manifest':str(p),'manifest_sha256':runner.digest(p),'items':{},'ceiling_usd':'5','destination':str(root/'execution'),'deadline_epoch':9999999999,'worker_window_seconds':1800,'worker_create_start_timeout_seconds':120,'worker_guard_command':['guard'],'worker_start_command':['start'],'worker_stop_command':['stop'],'native_capture':{'synthetic':True}}
            calls=[]
            def command(argv,**kw):
                calls.append((argv,kw))
                if argv==['start']:
                    (root/'execution/worker-post-start.json').write_text(json.dumps({'actual_started_at':'2026-11-18T08:00:00Z'}))
            with patch('runner.verify'),patch('runner.subprocess.run',side_effect=command),patch('native_capture.verify_startup') as startup,patch('runner.execute_items'),patch('native_capture.verify_shutdown'):
                runner.execute(b)
            from datetime import datetime
            expected=datetime.fromisoformat('2026-11-18T08:00:00+00:00').timestamp()+60
            self.assertEqual(startup.call_args.args[1],expected)
            self.assertEqual(next(kw['timeout'] for argv,kw in calls if argv==['start']),120)

if __name__=='__main__':unittest.main()
