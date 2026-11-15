import hashlib
import json
import tempfile
import unittest
from pathlib import Path
from checkpoint import RUNTIME, WORKSPACE, execute, validate_release
from check_record import disposition

class Checkpoint(unittest.TestCase):
    def setUp(self):
        self.items=[{'id':f'fixture-{n}','utterance':'Development fixture only.','group':g}
                    for n,g in enumerate(['fresh']*24+['control']*4+['regression']*6)]
        self.raw=json.dumps(self.items).encode()
        self.ids={'commit':'fixture-commit','harness_sha256':'fixture-harness','dependencies_sha256':'fixture-dependencies','runtime':RUNTIME}
        self.auth={**self.ids,'phase_b_approved':True,'workspace':WORKSPACE,'inputs_sha256':hashlib.sha256(self.raw).hexdigest()}

    def test_all_once_in_order_and_second_use_refused(self):
        seen=[]
        with tempfile.TemporaryDirectory() as d:
            result=execute(self.raw,self.auth,d,lambda:self.ids,lambda i:(seen.append(i['id']) or {'status':'completed'}),lambda:None)
            self.assertEqual(result,{'completed':34})
            self.assertEqual(seen,[i['id'] for i in self.items])
            with self.assertRaises(ValueError): execute(self.raw,self.auth,d,lambda:self.ids,lambda i:None,lambda:None)
            self.assertEqual(len(list(Path(d).glob('*.record.json'))),34)

    def test_identity_input_workspace_and_authority_rejected(self):
        for key in ['commit','harness_sha256','dependencies_sha256','runtime','workspace','inputs_sha256','phase_b_approved']:
            auth={**self.auth,key:'wrong'}
            with self.subTest(key=key),self.assertRaises(ValueError):validate_release(self.raw,auth,self.ids)

    def test_duplicate_and_partial_selection_refused(self):
        for items in [self.items[:33],[self.items[0]]*34]:
            raw=json.dumps(items).encode()
            with self.assertRaises(ValueError):validate_release(raw,{**self.auth,'inputs_sha256':hashlib.sha256(raw).hexdigest()},self.ids)

    def test_safe_rejections_retained_and_continue(self):
        records=[{'status':'failed','usage':{'planner_failure':{'category':'invalid_grounded_needs_plan','provider_status':200,'systemic':False}}},
                 {'status':'failed','usage':{'generation':{'category':'contract_validation_failure','provider_status':200}}}]
        for record in records:
            with tempfile.TemporaryDirectory() as d:
                self.assertEqual(execute(self.raw,self.auth,d,lambda:self.ids,lambda i:record,lambda:None),{'completed':34})
                self.assertEqual(len(list(Path(d).glob('*.record.json'))),34)

    def test_protected_and_unknown_failures_stop_and_preserve(self):
        records=[{'status':'failed'}, {'status':'completed','service_calls':[{'status':'connection_failed'}]},
                 {'status':'retrieval_no_answer','retrieval':{'clarification_reason':'active_corpus_changed'}},
                 {'status':'retrieval_no_answer','retrieval':{'clarification_reason':'deadline_exhausted'}},
                 {'status':'completed','service_calls':[{'status':'received','http_status':403}]}]
        for record in records:
            with tempfile.TemporaryDirectory() as d:
                result=execute(self.raw,self.auth,d,lambda:self.ids,lambda i:record,lambda:None)
                self.assertEqual(result['stopped_at'],1)
                self.assertEqual(len(list(Path(d).glob('*.record.json'))),1)

    def test_budget_failure_consumes_nothing(self):
        def exhausted():raise ValueError('budget_exhausted')
        with tempfile.TemporaryDirectory() as d:
            with self.assertRaises(ValueError):execute(self.raw,self.auth,d,lambda:self.ids,lambda i:None,exhausted)
            self.assertEqual(list(Path(d).iterdir()),[])

    def test_selector_failure_attribution(self):
        for code in ('invalid_output','incomplete_output'):
            record={'status':'retrieval_no_answer','retrieval':{'lineage':{'coverage_selection':{'status':'rejected','reason':code,'run_stop_required':False,'proposal_consumed':False}}},
                    'service_calls':[{'status':'received','http_status':503,'path':'/api/internal/retrieval/coverage-selection','coverage_failure':code}]}
            self.assertEqual(disposition(record)[0],'continue')
            record['retrieval']['lineage']['coverage_selection']['run_stop_required']=True
            self.assertEqual(disposition(record)[0],'stop')
        self.assertEqual(disposition({'status':'retrieval_no_answer','retrieval':{'lineage':{'coverage_selection':{'status':'rejected','reason':'unknown','run_stop_required':False,'proposal_consumed':False}}}})[0],'stop')

    def test_runtime_rechecked_between_items(self):
        count=0
        def verify():
            nonlocal count
            count+=1
            return self.ids if count<3 else {**self.ids,'runtime':'drift'}
        with tempfile.TemporaryDirectory() as d:
            with self.assertRaises(ValueError):execute(self.raw,self.auth,d,verify,lambda i:{'status':'completed'},lambda:None)
            self.assertEqual(len(list(Path(d).glob('*.record.json'))),1)

if __name__=='__main__':unittest.main()
