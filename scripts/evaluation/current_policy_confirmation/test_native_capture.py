import hashlib
import json
from pathlib import Path
import tempfile
import time
from types import SimpleNamespace
import unittest
from unittest.mock import patch

import native_capture as nc
import runner


def uid(n):
    return f'00000000-0000-4000-8000-{n:012d}'


class Fixture:
    def __init__(self, root):
        self.root = Path(root)
        for n in ('journal', 'health', 'capture'):
            (self.root/n).mkdir()
        self.spec = {n: str(self.root/n) for n in ('journal', 'health', 'capture')}
        self.spec.update(execution_id=uid(1), worker_id=uid(2), workspace_id=uid(3))
        self.seq = 0; self.prev = ''; self.files = 0
        self.row('worker_start')

    def row(self, kind, **fields):
        if kind in {'request_start','request_end'}:
            fields['purpose'] = 'retrieval.plan-needs' if fields['stage']=='planner' else 'generation.current-policy-answer'
        self.seq += 1
        v = {k: self.spec[k] for k in ('execution_id', 'worker_id', 'workspace_id')}
        v.update(schema='worker-observer-v1', sequence=self.seq, previous_sha256=self.prev, kind=kind, **fields)
        raw = (json.dumps(v)+'\n').encode()
        p = self.root/'journal'/f'{self.seq:06}.json'; p.write_bytes(raw)
        self.prev = hashlib.sha256(raw).hexdigest()
        return p

    def capture(self, rid, attempt=1, status='accepted', sequence=None, stage='planner', **extra):
        self.files += 1
        v = {'capture_version': 'evaluation-attempt-v1', 'workspace_id': uid(3), 'request_id': rid,
             'invocation_id': uid(int(rid[-12:])+1000), 'stage': stage, 'attempt': attempt,
             'event_sequence': sequence or attempt, 'status': status, **extra}
        p = self.root/'capture'/f'{rid}-{uid(self.files)}.json'
        p.write_text(json.dumps(v)); return p

    def job(self, number=11, attempts=None, generation=None, captures=True, outer=False, terminal=True, transport=False):
        common = {'run_id': uid(number), 'job_id': str(number)}
        self.row('job_start', **common)
        attempts = attempts or [{'attempt': 1, 'status': 'accepted'}]
        for offset in range(2 if outer else 1):
            rid = uid(number*10+offset)
            req = {**common, 'request_id': rid, 'stage': 'planner', 'endpoint': '/api/internal/retrieval/plan-needs'}
            self.row('request_start', **req)
            self.row('request_end', **req, http_status=None if transport else 200, transport='connection_failed' if transport else 'response', **({} if transport else {'attempts': attempts}))
            if captures:
                for a in attempts:self.capture(rid, **a)
        if generation:
            rid = uid(number*10+5)
            req = {**common, 'request_id': rid, 'stage': 'current_policy_generation', 'endpoint': '/api/internal/retrieval/generation/current-policy-answer'}
            self.row('request_start', **req)
            self.row('request_end', **req, http_status=200, transport='response', generation_status='failed' if generation=='reject' else 'completed', generation_reason='invalid_output' if generation=='reject' else None)
        if terminal:self.row('job_end', **common, job_state='processed', run_status='completed')
        return uid(number)

    def reconciler(self):return nc.Reconciler(self.spec, self.root, runner.durable)
    def after(self, r, run=uid(11), deadline=None):
        with patch('native_capture.time.sleep'):
            r.after('P', run, {'status':'completed'}, deadline or time.time()+.05)


class CaptureTests(unittest.TestCase):
    def test_one_corrected_outer_and_no_conditional_generation(self):
        for corrected, outer, generation in [(False,False,None),(True,False,None),(True,True,'success')]:
            with self.subTest(corrected=corrected,outer=outer,generation=generation), tempfile.TemporaryDirectory() as t:
                f=Fixture(t);r=f.reconciler();r.before('P')
                a=[{'attempt':1,'status':'rejected'},{'attempt':2,'status':'accepted'}] if corrected else None
                f.job(attempts=a,outer=outer,generation=generation);f.after(r)
                self.assertTrue((Path(t)/'P-native-attribution.json').exists());r.finish()
                f.row('worker_stop');nc.verify_shutdown(f.spec,Path(t),runner.durable)

    def test_missing_duplicate_gap_excess_status_invocation_workspace_version_truncation(self):
        changes={
            'missing':lambda v: None, 'duplicate':lambda v:v,
            'gap':lambda v:{**v,'attempt':2}, 'excess':lambda v:{**v,'attempt':3},
            'status':lambda v:{**v,'status':'rejected'},
            'workspace':lambda v:{**v,'workspace_id':uid(999)},
            'version':lambda v:{**v,'capture_version':'other'},
            'request':lambda v:{**v,'request_id':uid(999)},
            'invocation':lambda v:{**v,'invocation_id':'invalid'},
            'truncated':lambda v:{**v,'scope_diagnostics_truncated':True},
            'malformed':lambda v:'{',
        }
        for name,change in changes.items():
            with self.subTest(name=name), tempfile.TemporaryDirectory() as t:
                f=Fixture(t);r=f.reconciler();r.before('P');f.job()
                p=next((Path(t)/'capture').iterdir());v=json.loads(p.read_text());new=change(v)
                if new is None:p.unlink()
                elif name=='duplicate':f.capture(uid(110))
                else:p.write_text(new if isinstance(new,str) else json.dumps(new))
                with self.assertRaises(nc.CaptureError):f.after(r,deadline=time.time()+.002)
                self.assertFalse((Path(t)/'P-native-attribution.json').exists())

    def test_changed_missing_unexplained_late_concurrent_and_symlink(self):
        for mode in ['changed','removed','new','late','concurrent','symlink','directory']:
            with self.subTest(mode=mode),tempfile.TemporaryDirectory() as t:
                f=Fixture(t);r=f.reconciler();r.before('P');f.job();f.after(r)
                p=next((Path(t)/'capture').iterdir())
                if mode=='changed':p.write_text('{}')
                elif mode=='removed':p.unlink()
                elif mode=='late':f.capture(uid(110),attempt=2)
                elif mode=='symlink':(Path(t)/'capture'/'link').symlink_to('/etc/passwd')
                elif mode=='directory':(Path(t)/'capture'/'dir').mkdir()
                else:f.capture(uid(999))
                with self.assertRaises(nc.CaptureError):r.before('NEXT')

    def test_foreign_concurrent_during_item(self):
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t);r=f.reconciler();r.before('P');f.job();f.capture(uid(999))
            with self.assertRaisesRegex(nc.CaptureError,'unattributable'):f.after(r)

    def test_partial_write_completes_without_repair(self):
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t);r=f.reconciler();r.before('P');f.job()
            p=next((Path(t)/'capture').iterdir());original=p.read_text();p.write_text('{')
            # Simulates the native writer finishing its own final file, not reconciler repair.
            with patch('native_capture.time.sleep',side_effect=lambda _:p.write_text(original)):
                r.after('P',uid(11),{'status':'completed'},time.time()+.2)
            self.assertEqual(p.read_text(),original)

    def test_rejection_required_and_normalisation_optional(self):
        for present in [False,True]:
            with self.subTest(present=present),tempfile.TemporaryDirectory() as t:
                f=Fixture(t);r=f.reconciler();r.before('P');f.job(generation='reject')
                if present:f.capture(uid(115),stage='current_policy_generation',status='rejected',validation={'stage':'output_contract','field':'output','code':'structural_validation'})
                if present:f.after(r)
                else:
                    with self.assertRaisesRegex(nc.CaptureError,'missing_generation_rejection'):f.after(r,deadline=time.time()+.002)

    def test_preboundary_transport_is_halt_not_fabricated_missing_capture(self):
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t);r=f.reconciler();r.before('P');f.job(captures=False,transport=True)
            with self.assertRaisesRegex(nc.CaptureError,'transport_failure'):f.after(r)

    def test_interruption_health_journal_tamper_and_missing_shutdown(self):
        for mode in ['interrupted','health','journal_changed','missing_stop']:
            with self.subTest(mode=mode),tempfile.TemporaryDirectory() as t:
                f=Fixture(t);r=f.reconciler();r.before('P');f.job(terminal=mode!='interrupted')
                if mode=='health':(Path(t)/'health'/'observer-failed').write_text('observer_health_failure')
                if mode=='journal_changed':(Path(t)/'journal'/'000001.json').write_text('{}')
                if mode=='missing_stop':
                    f.after(r)
                    with self.assertRaisesRegex(nc.CaptureError,'missing_worker_stop'):nc.verify_shutdown(f.spec,Path(t),runner.durable)
                else:
                    with self.assertRaises(nc.CaptureError):f.after(r,deadline=time.time()+.002)

    def test_persisted_receipt_mismatch(self):
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t);r=f.reconciler();r.before('P');f.job()
            saved={'status':'completed','usage':{'current_policy':{'planner_usage':{'validation_attempts':[{'status':'rejected'}]}}}}
            with self.assertRaisesRegex(nc.CaptureError,'persisted_planner'):r.after('P',uid(11),saved,time.time()+.1)

    def test_distinct_native_invocations_and_rejection_optional_event_order(self):
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t);r=f.reconciler();r.before('P')
            f.job(attempts=[{'attempt':1,'status':'rejected'},{'attempt':2,'status':'accepted'}])
            p=sorted((Path(t)/'capture').iterdir())[1];v=json.loads(p.read_text());v['invocation_id']=uid(999);p.write_text(json.dumps(v))
            with self.assertRaisesRegex(nc.CaptureError,'multiple_invocations'):f.after(r)
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t);r=f.reconciler();r.before('P');f.job(generation='reject')
            f.capture(uid(115),stage='current_policy_generation',status='normalised',reason='exact_duplicate_evidence_ids',citation_normalisation=[{'part_index':0,'removed_count':1}])
            f.capture(uid(115),stage='current_policy_generation',status='rejected',sequence=2,validation={'stage':'citation_membership','field':'answer_parts.evidence_ids','code':'invalid_citation_membership'})
            f.after(r)

    def test_missing_planner_and_preboundary_http_failure(self):
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t);r=f.reconciler();r.before('P')
            common={'run_id':uid(11),'job_id':'11'}
            f.row('job_start',**common);f.row('job_end',**common,job_state='processed',run_status='completed')
            with self.assertRaisesRegex(nc.CaptureError,'missing_observed_planner'):f.after(r)
        with tempfile.TemporaryDirectory() as t:
            f=Fixture(t);r=f.reconciler();r.before('P');common={'run_id':uid(11),'job_id':'11'}
            f.row('job_start',**common)
            req={**common,'request_id':uid(110),'stage':'planner','endpoint':'/api/internal/retrieval/plan-needs'}
            f.row('request_start',**req);f.row('request_end',**req,http_status=401,transport='response',attempts=None)
            f.row('job_end',**common,job_state='processed',run_status='completed')
            with self.assertRaisesRegex(nc.CaptureError,'service_or_preboundary'):f.after(r)

    def test_required_observer_cannot_be_omitted_from_release(self):
        with self.assertRaisesRegex(ValueError,'observer not bound'):
            runner.verify({'require_native_capture':True},{})


class RunnerIntegrationTests(unittest.TestCase):
    def test_actual_runner_success_and_missing_capture_halts_before_second_dispatch(self):
        for missing in [False,True]:
            with self.subTest(missing=missing),tempfile.TemporaryDirectory() as t:
                f=Fixture(t);root=Path(t);seen=[]
                bound={'actor':uid(4),'browser_context':{'workspace':uid(3),'fresh_conversation':True},'expected_preflight':{'profile':{},'run_timeout_seconds':900},'maximum_liability_by_stage':{'planning_including_intrinsic_calls':'1','retrieval_including_retries':'1','generation':'1'}}
                b={'provider_free':True,'native_capture':f.spec,'deadline_epoch':time.time()+60,'minimum_item_window_seconds':0,'items':{'P1':bound,'P2':bound},'ceiling_usd':'300','preflight_command':['preflight'],'capture_command':['capture']}
                manifest={'items':[{'id':'P1','utterance':'Synthetic first'},{'id':'P2','utterance':'Synthetic second'}]}
                def dispatch(argv,**kwargs):
                    if argv==['preflight']:return SimpleNamespace(stdout=json.dumps(bound['expected_preflight']))
                    if argv[0]=='node':
                        inp=json.loads(Path(argv[-1]).read_text());seen.append(inp['id']);n=10+len(seen)
                        ledger=json.loads((root/'ledger.json').read_text());self.assertEqual(ledger[-1]['state'],'reserved_before_dispatch')
                        f.job(number=n,captures=not missing)
                        out=Path(argv[-2]);out.mkdir()
                        (out/'records.json').write_text(json.dumps([{'backend':{'data':{'id':uid(n+100),'runs':[{'id':uid(n)}]}},'completed':{'text':'Synthetic saved output'},'timing':{'submitted_at_ms':(time.time()-914.95 if missing else time.time())*1000}}]))
                        return SimpleNamespace()
                    return SimpleNamespace(stdout=json.dumps({'status':'completed','run_id':uid(10+len(seen)),'text':'Synthetic saved output','needs_profile':{}}))
                with patch('runner.verify'),patch('runner.subprocess.run',side_effect=dispatch),patch('native_capture.time.sleep'):
                    if missing:
                        with self.assertRaises(nc.CaptureError):runner.execute_items(b,manifest,root)
                        self.assertEqual(seen,['P1']);self.assertTrue((root/'P1'/'persisted.json').exists())
                        self.assertEqual(json.loads((root/'ledger.json').read_text())[0]['liability_usd'],'3')
                        self.assertTrue((root/'P1-native-halt.json').exists())
                        with self.assertRaises(FileExistsError):runner.execute_items(b,manifest,root)
                    else:runner.execute_items(b,manifest,root);self.assertEqual(seen,['P1','P2'])


if __name__=='__main__':unittest.main()
