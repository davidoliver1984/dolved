import json
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

import runner


class ConfirmationPreparationTests(unittest.TestCase):
    def test_exact_68_shape_and_order_without_cohort_fields(self):
        for count in [67,68,69]:
            with self.subTest(count=count), tempfile.TemporaryDirectory() as temp:
                p=Path(temp)/'synthetic.json'
                manifest={'schema_version':1,'package_id':'synthetic-only','note':'No released questions',
                          'evaluated_at_assumption':'2026-11-17','items':[{'id':f'P{i:03}','utterance':f'Synthetic request {i}'} for i in range(count)]}
                p.write_text(json.dumps(manifest))
                b={'manifest':str(p),'manifest_sha256':runner.digest(p),'expected_item_count':68,
                   'files':{'synthetic.json':runner.digest(p)},'worktree':temp,'commit':'controlled',
                   'mounted_verify_command':['controlled'],'deadline_epoch':9999999999}
                with patch('runner.subprocess.check_output',return_value='controlled\n'),patch('runner.subprocess.run') as call:
                    if count==68:
                        runner.verify(b,manifest);call.assert_called_once()
                        self.assertEqual([x['id'] for x in manifest['items']],[f'P{i:03}' for i in range(68)])
                    else:
                        with self.assertRaisesRegex(ValueError,'manifest count'):runner.verify(b,manifest)
                        call.assert_not_called()

    def test_window_boundary_refuses_before_reservation_or_browser(self):
        with patch('runner.time.time',return_value=100):
            runner.require_item_window({'deadline_epoch':1195,'minimum_item_window_seconds':1095})
            with self.assertRaisesRegex(ValueError,'remaining item window'):
                runner.require_item_window({'deadline_epoch':1194.99,'minimum_item_window_seconds':1095})
        with tempfile.TemporaryDirectory() as temp:
            b={'deadline_epoch':1100,'minimum_item_window_seconds':1095,'preflight_command':['controlled'],
               'items':{'P':{'actor':'test','browser_context':{'workspace':'test'},'expected_preflight':{},
                            'maximum_liability_by_stage':{'planning_including_intrinsic_calls':'1','retrieval_including_retries':'1','generation':'1'}}},'ceiling_usd':'300'}
            with patch('runner.verify'),patch('runner.time.time',return_value=100),patch('runner.subprocess.run',return_value=SimpleNamespace(stdout='{}')) as calls:
                with self.assertRaisesRegex(ValueError,'remaining item window'):
                    runner.execute_items(b,{'items':[{'id':'P','utterance':'Synthetic'}]},Path(temp))
                self.assertEqual(calls.call_count,1)
                self.assertFalse((Path(temp)/'ledger.json').exists())

    def test_68_controlled_items_reserve_once_preserve_order_and_fresh_context(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);ids=[f'P{i:03}' for i in range(68)];seen=[]
            manifest={'items':[{'id':i,'utterance':'Synthetic '+i} for i in ids]}
            bound={'actor':'test','browser_context':{'workspace':'test','fresh_conversation':True},'expected_preflight':{'run_timeout_seconds':900,'profile':{}},'maximum_liability_by_stage':{'planning_including_intrinsic_calls':'1','retrieval_including_retries':'1','generation':'1'}}
            b={'provider_free':True,'deadline_epoch':9999999999,'minimum_item_window_seconds':1095,'items':{i:bound for i in ids},'ceiling_usd':'300','preflight_command':['preflight'],'capture_command':['capture']}
            def call(argv,**kwargs):
                if argv==['preflight']:return SimpleNamespace(stdout=json.dumps(bound['expected_preflight']))
                if argv[0]=='node':
                    inp=json.loads(Path(argv[-1]).read_text());i=inp['id'];seen.append(i)
                    self.assertTrue(inp['fresh_conversation']);self.assertEqual(inp['utterance'],'Synthetic '+i)
                    ledger=json.loads((root/'ledger.json').read_text());self.assertEqual(ledger[-1]['id'],i);self.assertEqual(ledger[-1]['state'],'reserved_before_dispatch')
                    dest=Path(argv[-2]);dest.mkdir()
                    (dest/'records.json').write_text(json.dumps([{'backend':{'data':{'id':'conversation-'+i,'runs':[{'id':'run-'+i}]}},'completed':{'text':'Controlled saved answer'}}]))
                    return SimpleNamespace()
                i=seen[-1]
                return SimpleNamespace(stdout=json.dumps({'status':'completed','run_id':'run-'+i,'text':'Controlled saved answer','needs_profile':{}}))
            with patch('runner.verify'),patch('runner.subprocess.run',side_effect=call):runner.execute_items(b,manifest,root)
            self.assertEqual(seen,ids);self.assertEqual(len(set(seen)),68)
            self.assertEqual(json.loads((root/'completed.json').read_text())['items'],68)


if __name__=='__main__':unittest.main()
