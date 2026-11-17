import json
from pathlib import Path
import tempfile
import unittest
from decimal import Decimal
from unittest.mock import patch
from types import SimpleNamespace
import runner


class RunnerTests(unittest.TestCase):
    def test_reservation_precedes_dispatch_and_missing_receipts_keep_liability(self):
        rows=[]
        runner.reserve(rows,'one',Decimal('2'),Decimal('3'))
        with self.assertRaisesRegex(ValueError,'budget refusal'):
            runner.reserve(rows,'two',Decimal('2'),Decimal('3'))
        self.assertEqual([r['id'] for r in rows],['one'])
        self.assertEqual(rows[0]['liability_usd'],'2')

    def test_duplicate_item_refused(self):
        rows=[]
        runner.reserve(rows,'one',Decimal('1'),Decimal('9'))
        with self.assertRaisesRegex(ValueError,'duplicate'):
            runner.reserve(rows,'one',Decimal('1'),Decimal('9'))

    def test_exclusive_start_cannot_be_overwritten(self):
        with tempfile.TemporaryDirectory() as directory:
            file=Path(directory)/'started.json'
            runner.durable(file,{'first':True},True)
            with self.assertRaises(FileExistsError):
                runner.durable(file,{'second':True},True)
            self.assertEqual(json.loads(file.read_text()),{'first':True})

    def test_drift_refused_before_executable_callback(self):
        with tempfile.TemporaryDirectory() as directory:
            file=Path(directory)/'manifest.json';file.write_text('{}')
            with patch('runner.subprocess.run') as dispatch:
                with self.assertRaisesRegex(ValueError,'manifest drift'):
                    runner.verify({'manifest':str(file),'manifest_sha256':'wrong'}, {})
                dispatch.assert_not_called()

    def test_released_shape_requires_no_cohorts(self):
        with tempfile.TemporaryDirectory() as directory:
            manifest={'schema_version':1,'package_id':'development-only','note':'synthetic',
                      'evaluated_at_assumption':'2026-11-16','items':[{'id':'D1','utterance':'What is the current rule?'}]}
            file=Path(directory)/'manifest.json';file.write_text(json.dumps(manifest))
            binding={'manifest':str(file),'manifest_sha256':runner.digest(file),'provider_free':True,'expected_item_count':1,
                     'files':{'manifest.json':runner.digest(file)},'worktree':directory,'commit':'test',
                     'mounted_verify_command':['test-only'],'deadline_epoch':9999999999}
            with patch('runner.subprocess.check_output',return_value='test\n'),patch('runner.subprocess.run') as verify:
                runner.verify(binding,manifest)
                verify.assert_called_once()
            manifest['items'][0]['cohort']='forbidden'
            with self.assertRaisesRegex(ValueError,'item shape'):
                runner.verify(binding,manifest)

    def test_six_item_binding_rejects_five_and_seven_before_callbacks(self):
        for count in [5,7]:
            with self.subTest(count=count),tempfile.TemporaryDirectory() as directory:
                manifest={'schema_version':1,'package_id':'synthetic','note':'test','evaluated_at_assumption':'fixed',
                          'items':[{'id':str(i),'utterance':'Synthetic current request'} for i in range(count)]}
                file=Path(directory)/'manifest.json';file.write_text(json.dumps(manifest))
                with patch('runner.subprocess.run') as callback:
                    with self.assertRaisesRegex(ValueError,'manifest count'):
                        runner.verify({'manifest':str(file),'manifest_sha256':runner.digest(file),'expected_item_count':6},manifest)
                    callback.assert_not_called()

    def test_six_item_binding_accepts_exact_released_order(self):
        with tempfile.TemporaryDirectory() as directory:
            manifest={'schema_version':1,'package_id':'synthetic','note':'test','evaluated_at_assumption':'fixed',
                      'items':[{'id':str(i),'utterance':'Synthetic current request'} for i in range(6)]}
            file=Path(directory)/'manifest.json';file.write_text(json.dumps(manifest))
            binding={'manifest':str(file),'manifest_sha256':runner.digest(file),'expected_item_count':6,
                     'files':{'manifest.json':runner.digest(file)},'worktree':directory,'commit':'test',
                     'mounted_verify_command':['test-only'],'deadline_epoch':9999999999}
            with patch('runner.subprocess.check_output',return_value='test\n'),patch('runner.subprocess.run') as callback:
                runner.verify(binding,manifest);callback.assert_called_once()
            self.assertEqual([x['id'] for x in manifest['items']],list(map(str,range(6))))

    def test_preparation_is_retained_but_operational_state_refused(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);(root/'capture').mkdir();(root/'report.md').write_text('Frozen preparation')
            (root/'binding.json').write_text('{}')
            binding={'destination':directory,'binding_sha256':runner.digest(root/'binding.json'),
                     'preparation_files':{'report.md':runner.digest(root/'report.md')}}
            self.assertEqual(runner.prepare_destination(binding),root)
            for name in ['started.json','ledger.json','S1-browser-input.json','capture/prior.json']:
                file=root/name;file.write_text('{}')
                with self.assertRaisesRegex(ValueError,'operational'):runner.prepare_destination(binding)
                file.unlink()
            (root/'report.md').write_text('changed')
            with self.assertRaisesRegex(ValueError,'evidence drift'):runner.prepare_destination(binding)

    def test_failed_dispatch_preserves_reservation_and_prevents_restart(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory); manifest=root/'manifest.json'
            manifest.write_text(json.dumps({'items':[{'id':'D1','utterance':'Synthetic question'}], 'evaluated_at_assumption':'fixed'}))
            binding={'provider_free':True,'manifest':str(manifest),'manifest_sha256':runner.digest(manifest),
                     'deadline_epoch':9999999999,
                     'destination':str(root/'run'),'ceiling_usd':'10','preflight_command':['controlled'], 'items':{'D1':{'actor':'test','expected_preflight':{'run_timeout_seconds':900},'maximum_liability_by_stage':{
                         'planning_including_intrinsic_calls':'1','retrieval_including_retries':'2','generation':'3'},
                         'browser_context':{'controlled':True,'workspace':'test'}}}}
            with patch('runner.verify'),patch('runner.subprocess.run',side_effect=[SimpleNamespace(stdout='{"run_timeout_seconds":900}'),RuntimeError('controlled stop')]):
                with self.assertRaises(RuntimeError):runner.execute(binding)
                row=json.loads((root/'run/ledger.json').read_text())[0]
                self.assertEqual(row['liability_usd'],'6')
                self.assertEqual(row['state'],'halt_uncertain_or_failed_capture')
                with self.assertRaises(FileExistsError):runner.execute(binding)

    def test_only_typed_safe_item_failures_continue(self):
        for reason in ['provider_unavailable','provider_authentication','service_transport','unconfigured']:
            self.assertFalse(runner.safe_item_outcome({'status':'completed','answer':{'temporal_context':{'current_policy':{'generation':{'status':'failed','reason':reason}}}}}))
        self.assertTrue(runner.safe_item_outcome({'status':'failed','usage':{'planner_failure':{
            'category':'invalid_grounded_needs_plan','provider_status':200,'systemic':False}}}))
        for status in ['failed','cancelled','queued','invented']:
            self.assertFalse(runner.safe_item_outcome({'status':status}))
        self.assertFalse(runner.safe_item_outcome({'status':'failed','usage':{'planner_failure':{
            'category':'invalid_grounded_needs_plan','provider_status':503,'systemic':False}}}))

    def test_consumed_authorisation_refused_before_dispatch(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory); manifest=root/'manifest.json'; authority=root/'test-authority.json'
            manifest.write_text(json.dumps({'items':[]}))
            authority.write_text(json.dumps({'binding_sha256':'test-only','approved':True}))
            Path(str(authority)+'.consumed').mkdir()
            binding={'provider_free':False,'manifest':str(manifest),'items':{},'ceiling_usd':'300',
                     'authorization':str(authority),'authorization_sha256':runner.digest(authority),'binding_sha256':'test-only'}
            with patch('runner.verify'),patch('runner.subprocess.run') as dispatch:
                with self.assertRaisesRegex(ValueError,'already consumed'):runner.execute(binding)
                dispatch.assert_not_called()

    def test_worker_is_after_consumption_and_stopped_on_item_failure(self):
        with tempfile.TemporaryDirectory() as directory:
            root=Path(directory);manifest=root/'manifest.json';authority=root/'authority.json'
            manifest.write_text(json.dumps({'items':[{'id':'D1','utterance':'Synthetic'}],'evaluated_at_assumption':'fixed'}))
            authority.write_text(json.dumps({'binding_sha256':'approved-binding','approved':True}))
            binding={'provider_free':False,'manifest':str(manifest),'manifest_sha256':runner.digest(manifest),
                     'items':{'D1':{'maximum_liability_by_stage':{'planning_including_intrinsic_calls':'1','retrieval_including_retries':'1','generation':'1'}}},'ceiling_usd':'25','destination':str(root/'run'),'deadline_epoch':9999999999,
                     'authorization':str(authority),'authorization_sha256':runner.digest(authority),'binding_sha256':'approved-binding',
                     'pricing_snapshot_sha256':'synthetic','reviewer_context_sha256':'synthetic',
                     'worker_window_seconds':9000,'worker_guard_command':['guard'],'worker_start_command':['start'],
                     'worker_stop_command':['stop']}
            observed=[]
            def callback(argv,**kwargs):
                self.assertTrue((root/'run/started.json').is_file());self.assertTrue(Path(str(authority)+'.consumed').is_dir());observed.append(argv)
            with patch('runner.verify'),patch('runner.subprocess.run',side_effect=callback),patch('runner.execute_items',side_effect=RuntimeError('controlled failure')):
                with self.assertRaises(RuntimeError):runner.execute(binding)
            self.assertEqual(observed,[['guard'],['start'],['stop']])
            timing=json.loads((root/'run/worker-timing.json').read_text())
            self.assertEqual(timing['deadline_epoch'],timing['worker_start_epoch']+9000)


if __name__=='__main__':unittest.main()
