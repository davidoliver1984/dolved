import json
from pathlib import Path
import tempfile
import time
from types import SimpleNamespace
import unittest
from unittest.mock import patch
import native_capture as nc
import runner
from test_native_capture import Fixture


class StartupTests(unittest.TestCase):
    def test_startup_requires_complete_healthy_start_before_reservation(self):
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t)
            f.row('worker_loop_ready')
            with patch('native_capture.time.sleep'):
                nc.verify_startup(f.spec,time.time()+.1,Path(t),runner.durable)
            self.assertTrue(json.loads((Path(t)/'observer-startup.json').read_text())['before_item_reservation'])
            self.assertFalse((Path(t)/'ledger.json').exists())
        for mode in ['missing','partial','health','active']:
            with self.subTest(mode=mode),tempfile.TemporaryDirectory() as t:
                f=Fixture(t);p=Path(t)/'journal'/'000001.json'
                if mode=='missing':p.unlink()
                elif mode=='partial':p.write_text('{')
                elif mode=='health':(Path(t)/'health'/'failure').write_text('observer_health_failure')
                else:f.job()
                with patch('native_capture.time.sleep'),self.assertRaises(nc.CaptureError):
                    nc.verify_startup(f.spec,time.time()+.002,Path(t),runner.durable)

    def test_separate_operational_roots_are_fresh_and_restart_refused(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t)/'execution';root.mkdir()
            spec={}
            for k,n in [('capture','capture'),('journal','observer-journal'),('health','observer-health')]:
                (root/n).mkdir();spec[k]=str(root/n)
            b={'destination':str(root),'prepared_empty_destination':True,'native_capture':spec}
            self.assertEqual(runner.prepare_destination(b),root)
            (root/'started.json').write_text('{}')
            with self.assertRaisesRegex(ValueError,'operational state'):runner.prepare_destination(b)

    def test_one_item_manifest_and_wrong_counts(self):
        for count in [0,1,2]:
            with self.subTest(count=count),tempfile.TemporaryDirectory() as t:
                root=Path(t);m={'schema_version':1,'package_id':'synthetic','note':'controlled','evaluated_at_assumption':'2026-11-17','items':[{'id':str(n),'utterance':'Synthetic'} for n in range(count)]}
                p=root/'m.json';p.write_text(json.dumps(m));b={'manifest':str(p),'manifest_sha256':runner.digest(p),'expected_item_count':1,'files':{'m.json':runner.digest(p)},'worktree':t,'commit':'test','mounted_verify_command':['controlled'],'deadline_epoch':9999999999}
                with patch('runner.subprocess.check_output',return_value='test'),patch('runner.subprocess.run'):
                    if count==1:runner.verify(b,m)
                    else:
                        with self.assertRaisesRegex(ValueError,'manifest count'):runner.verify(b,m)

    def test_execute_orders_startup_before_items_and_cleanup_after_failure(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);p=root/'manifest.json';p.write_text(json.dumps({'items':[{'id':'SYN','utterance':'Synthetic'}],'evaluated_at_assumption':'fixed'}))
            b={'provider_free':True,'manifest':str(p),'manifest_sha256':runner.digest(p),'items':{'SYN':{'maximum_liability_by_stage':{'planning_including_intrinsic_calls':'1','retrieval_including_retries':'1','generation':'1'}}},'ceiling_usd':'5','destination':str(root/'execution'),'deadline_epoch':9999999999,'worker_window_seconds':1800,'worker_guard_command':['guard'],'worker_start_command':['start'],'worker_stop_command':['stop'],'native_capture':{'synthetic':True}}
            seen=[]
            def command(argv,**kw):seen.append(argv[0]);return SimpleNamespace()
            def start(*args):seen.append('startup');self.assertTrue((root/'execution'/'started.json').exists());self.assertFalse((root/'execution'/'ledger.json').exists())
            def items(*args):seen.append('items');raise RuntimeError('controlled')
            with patch('runner.verify'),patch('runner.subprocess.run',side_effect=command),patch('native_capture.verify_startup',side_effect=start),patch('runner.execute_items',side_effect=items),patch('native_capture.verify_shutdown',side_effect=lambda *a:seen.append('shutdown')):
                with self.assertRaises(RuntimeError):runner.execute(b)
            self.assertEqual(seen,['guard','start','startup','items','stop','shutdown'])

    def test_primary_failure_survives_cleanup_failure(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);p=root/'manifest.json';p.write_text(json.dumps({'items':[],'evaluated_at_assumption':'fixed'}))
            b={'provider_free':True,'manifest':str(p),'manifest_sha256':runner.digest(p),'items':{},'ceiling_usd':'5','destination':str(root/'execution'),'deadline_epoch':9999999999,'worker_window_seconds':1800,'worker_guard_command':['guard'],'worker_start_command':['start'],'worker_stop_command':['stop']}
            def run(argv,**kw):
                if argv[0]=='start':raise ValueError('primary-controlled')
                if argv[0]=='stop':raise RuntimeError('cleanup-controlled')
            with patch('runner.verify'),patch('runner.subprocess.run',side_effect=run):
                with self.assertRaisesRegex(ValueError,'primary-controlled'):runner.execute(b)
            self.assertTrue((root/'execution/primary-halt.json').exists())
            self.assertTrue(json.loads((root/'execution/observer-shutdown-halt.json').read_text())['primary_failure_preserved'])

    def test_distinct_startup_and_ready_shutdown_failures(self):
        for ready,code in [(False,'startup_interrupted_before_loop_ready'),(True,'ready_worker_missing_stop')]:
            with tempfile.TemporaryDirectory() as t:
                f=Fixture(t);f.spec['require_loop_ready']=True
                if ready:f.row('worker_loop_ready')
                with self.assertRaisesRegex(nc.CaptureError,code):nc.verify_shutdown(f.spec,Path(t),runner.durable)


if __name__=='__main__':unittest.main()
