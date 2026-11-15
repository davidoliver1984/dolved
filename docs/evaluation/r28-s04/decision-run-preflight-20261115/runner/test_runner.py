"""Provider-free callbacks through the real checkpoint; only host/provider boundaries fake."""
import hashlib
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import runner as r
from accounting import Accounting, CAP, D, atomic

ROOT = Path('/workspace')


class Wiring(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(); self.addCleanup(self.tmp.cleanup)
        self.dest = Path(self.tmp.name); (self.dest/'capture').mkdir()
        self.seen=[]; self.verify_count=0; self.fail_at=None; self.record_override={}
        mechanical=json.loads((r.PREFLIGHT/'mechanical/identity.json').read_text())
        raw=(ROOT/'docs/evaluation/r28-s04/decision-run-20261115/released-input/questions-only.json').read_bytes()
        self.auth={'execution_baseline':r.BASELINE,'commit':'fixture-commit','workspace':r.WORKSPACE,'actor':r.ACTOR,'generation':r.GENERATION,
                   'reviewer_confirmation_sha256':r.REVIEW,'reservation_usd':'136.737303','hard_stop_usd':'273.474606',
                   'harness_sha256':r.digest(r.HERE/'executable.sha256'),'dependencies_sha256':mechanical['dependencies_sha256'],
                   'runtime':mechanical['runtime_sha256'],'released_input_sha256':hashlib.sha256(raw).hexdigest(),
                   'inputs_sha256':hashlib.sha256(json.dumps(r.parse_release(raw),ensure_ascii=False,separators=(',',':')).encode()).hexdigest(),
                   'execution_context_confirmed':True,'phase_b_approved':True}
        self.runner=r.Runner(ROOT,self.dest,self.auth,self.bridge)

    def bridge(self, operation, value):
        if operation=='VERIFY':
            self.verify_count+=1
            folder=self.dest/'integrity'/value;folder.mkdir(parents=True)
            context=json.loads((r.PREFLIGHT/'mechanical/context.json').read_text())
            atomic(folder/'context.json',context)
            atomic(folder/'preflight.json',{'corpus':{'id':r.GENERATION}})
            atomic(folder/'host.json',{'commit':'drift' if self.fail_at==self.verify_count else 'fixture-commit',
                 'branch':'codex/block1-extractive-hold-20261112','override_sha256':json.loads((r.PREFLIGHT/'mechanical/recovery.json').read_text())['override_sha256']})
            atomic(folder/'running.json',{'api':{'all_environment_values_verified':True,'healthy':True},'ai':{'all_environment_values_verified':True,'healthy':True}})
        elif operation=='DISPATCH':
            self.seen.append(value); folder=self.dest/'items'/value
            record={'input':json.loads((folder/'input.json').read_text()),'run_id':'fixture-'+value,'status':'completed',
                    'displayed_text':'Fixture only.','reloaded_rendered_text':'Fixture only.','temporal_context':[],
                    'reloaded_temporal_context':None,'service_calls':[],'production_trace':{},**self.record_override}
            atomic(folder/'raw.json',record)
            entry={'sequence':1,'previous_hash':''};unhashed=json.dumps(entry,separators=(',',':'))
            entry['hash']=hashlib.sha256(unhashed.encode()).hexdigest()
            (folder/'ledger.ndjson').write_text(json.dumps(entry,separators=(',',':'))+'\n')
        elif operation=='PERSIST':
            folder=self.dest/'items'/value;record=json.loads((folder/'raw.json').read_text())
            atomic(folder/'persistence.json',{'items':[{'workspace_id':r.WORKSPACE,'actor_id':r.ACTOR,'question':record['input']['utterance'],
                'user_turns':1,'generation_runs':1,'run_id':record['run_id'],'assistant_display_text':record['displayed_text'],
                'reloaded_rendered_text':record['reloaded_rendered_text']}]})
        else: raise AssertionError(operation)

    def test_order_all_once_and_no_second_use(self):
        self.assertEqual(self.runner.run(),{'completed':34})
        self.assertEqual(self.seen,[i['id'] for i in self.runner.items])
        self.assertEqual(len(list(self.dest.glob('*.started.json'))),34)
        with self.assertRaisesRegex(ValueError,'prior_run_state'):self.runner.run()
        ledger=json.loads((self.dest/'accounting.json').read_text())
        self.assertEqual(len(ledger['items']),34)
        self.assertLessEqual(D(ledger['conservative_committed_usd']),D('136.737303'))
        self.assertTrue(all(v['unknown_liability_usd']==v['maximum_usd'] for v in ledger['items'].values()))

    def test_prior_start_consumption_and_records_refused_without_dispatch(self):
        for marker in ('O1.started.json','authorization-consumed.json','O1.record.json'):
            with self.subTest(marker=marker):
                path=self.dest/marker;path.write_text('{}')
                with self.assertRaisesRegex(ValueError,'prior_run_state'):self.runner.run()
                path.unlink()
        self.assertEqual(self.seen,[])

    def test_budget_stop_before_any_dispatch_or_consumption(self):
        self.runner.account.state['items']['prior']={'charged_liability_usd':str(CAP-1)}
        with self.assertRaisesRegex(ValueError,'budget_exhausted_before_dispatch'):self.runner.run()
        self.assertEqual(self.seen,[])
        self.assertFalse((self.dest/'authorization-consumed.json').exists())

    def test_drift_between_items_preserves_settled_and_pending_liability(self):
        self.fail_at=3
        with self.assertRaisesRegex(ValueError,'git_drift'):self.runner.run()
        self.assertEqual(self.seen,['O1'])
        ledger=json.loads((self.dest/'accounting.json').read_text())
        self.assertEqual(ledger['items']['O1']['status'],'recorded')
        self.assertEqual(len(list(self.dest.glob('*.record.json'))),1)

    def test_interrupted_dispatch_retains_full_reservation(self):
        original=self.bridge
        def broken(op,value):
            if op=='DISPATCH':raise RuntimeError('fixture_transport_failure')
            return original(op,value)
        self.runner.bridge=broken
        with self.assertRaises(RuntimeError):self.runner.run()
        again=Accounting(self.dest/'accounting.json',json.loads((r.PREFLIGHT/'mechanical/reservation.json').read_text()))
        self.assertEqual(again.state['items']['O1']['status'],'reserved')
        self.assertEqual(again.total(),again.maximum)
        self.assertTrue((self.dest/'O1.started.json').exists())

    def test_safe_model_outcome_continues_but_typed_infrastructure_halts(self):
        self.record_override={'status':'failed','usage':{'planner_failure':{'category':'invalid_grounded_needs_plan','provider_status':200,'systemic':False}}}
        # Stop on the next verification after proving the first safe result continued.
        self.fail_at=3
        with self.assertRaisesRegex(ValueError,'git_drift'):self.runner.run()
        self.assertEqual(json.loads((self.dest/'O1.verdict.json').read_text())['action'],'continue')

    def test_unknown_http_halts_and_persists_ledger(self):
        self.record_override={'status':'failed','service_calls':[{'key':'fixture','path':'/api/internal/compare-family','status':'received','http_status':503}]}
        result=self.runner.run()
        self.assertEqual(result['stopped_at'],1);self.assertEqual(self.seen,['O1'])
        self.assertEqual(json.loads((self.dest/'accounting.json').read_text())['items']['O1']['status'],'recorded')

    def test_missing_receipt_and_over_reservation_cost_never_disappear(self):
        a=self.runner.account;a.reserve('fixture')
        a.settle('fixture',{'service_calls':[{'key':'x','path':'fixture','usage':None}],'production_trace':{}})
        self.assertEqual(a.total(),a.maximum)
        a.reserve('expensive')
        with self.assertRaisesRegex(ValueError,'item_reservation_exceeded'):
            a.settle('expensive',{'service_calls':[{'key':'x','path':'fixture','usage':{'cost_usd':10}}],'production_trace':{}})
        self.assertEqual(D(json.loads(a.path.read_text())['items']['expensive']['charged_liability_usd']),D(10))

    def test_context_and_confirmation_drift_refused(self):
        self.auth['reviewer_confirmation_sha256']='wrong'
        with self.assertRaisesRegex(ValueError,'authority_binding'):self.runner.run()
        self.assertEqual(self.seen,[])

    def test_receipt_estimate_not_double_counted_with_parent(self):
        from accounting import receipts
        rec={'service_calls':[{'key':'x','path':'/answer-needs','usage':{'model':'gpt-5-mini','input_tokens':100,'output_tokens':10}}],
             'usage':{'cost_usd':100},'generation_usage':{'cost_usd':100},'production_trace':{}}
        self.assertEqual(len(receipts(rec)),1)
        self.assertEqual(D(receipts(rec)[0]['known_or_estimated_usd']),D('.000045'))

    def test_successful_generation_needs_no_rejection_sidecar(self):
        self.record_override={'service_calls':[{'key':'g','path':'/api/internal/generation/answer-needs','request_id':'00000000-0000-4000-8000-000000000011','status':'received','http_status':200}]}
        self.fail_at=3
        with self.assertRaisesRegex(ValueError,'git_drift'):self.runner.run()
        self.assertTrue((self.dest/'O1.record.json').exists())

    def test_reported_generation_rejection_requires_sidecar(self):
        self.record_override={'service_calls':[{'key':'g','path':'/api/internal/generation/answer-needs','request_id':'00000000-0000-4000-8000-000000000011','status':'received','http_status':503,'error_category':'contract_validation_failure'}]}
        with self.assertRaisesRegex(ValueError,'missing_attempt_capture'):self.runner.run()
        self.assertEqual(json.loads((self.dest/'accounting.json').read_text())['items']['O1']['status'],'recorded')


if __name__=='__main__':unittest.main()
