import copy
import hashlib
import json
import os
from pathlib import Path
import unittest
from unittest.mock import Mock
from mount_guard import build_mount_spec,verify_mounts,mount_mismatches,API_BIND_EQUIVALENCES
from rehearsal_runner import identity_mismatches,start_verified
from test_mount_guard import MountGuardTests

class SharedWorkerIdentityTests(unittest.TestCase):
    def test_retained_real_mounts_and_all_approved_spelling_combinations(self):
        f=json.loads(Path(os.environ['RETAINED_MOUNT_FIXTURE']).read_text())
        config,_,bound=MountGuardTests().fixture()
        # Corpus is copied from the exact retained release, not inferred from
        # arbitrary prefixes. All other mounts retain their recorded identities.
        bound['corpus_bind_equivalence']['source']='/private/tmp/dolved-r28-s03.zU3HX6/corpus/eval-corpus-v4-authoring'
        config['services']['api']['volumes']=[];config['volumes']={}
        for m in f['api']:
            t=m['Destination'];row={'target':t,'type':m['Type'],'read_only':not m['RW']}
            if t in API_BIND_EQUIVALENCES:row['source']=API_BIND_EQUIVALENCES[t]['source']
            elif t=='/r28-corpus':row['source']=bound['corpus_bind_equivalence']['source']
            elif m['Type']=='volume':row['source']=m['Name'];config['volumes'][m['Name']]={'name':m['Name']}
            else:row['source']=m['Source']
            config['services']['api']['volumes'].append(row)
        spec=build_mount_spec(config,bound)
        worker=[m for m in f['worker'] if m['Destination'] in spec]
        for a_prefix,w_prefix in [(True,False),(False,True),(False,False),(True,True)]:
            for rows,prefix in [(copy.deepcopy(f['api']),a_prefix),(copy.deepcopy(worker),w_prefix)]:
                for m in rows:
                    if m['Destination'] in API_BIND_EQUIVALENCES:m['Source']=('/host_mnt' if prefix else '')+API_BIND_EQUIVALENCES[m['Destination']]['source']
                verify_mounts(spec,rows)
        changed=copy.deepcopy(worker);changed[0]['Source']='/host_mnt/host_mnt'+API_BIND_EQUIVALENCES['/app']['source']
        self.assertIn('mount_source_mismatch',mount_mismatches(spec,changed))
        missing=copy.deepcopy(worker);missing.pop();self.assertIn('mount_set_mismatch',mount_mismatches(spec,missing))

    def fixture(self):
        config,c,bound=MountGuardTests().fixture()
        spec=build_mount_spec(config,bound)
        b={'image':'image','command':['php','rehearsal'],'entrypoint':['entry'],'owner_id':'owned','mount_spec':spec,'environment_sha256':hashlib.sha256(json.dumps({'A':'a=b','B':''},sort_keys=True).encode()).hexdigest()}
        c.update(Id='exact-id',Image='image',Config={'Cmd':b['command'],'Entrypoint':b['entrypoint'],'Env':['B=','A=a=b'],'Labels':{'dolved.rehearsal-owner':'owned'}},State={'Running':False,'Status':'created','StartedAt':'0001-01-01T00:00:00Z'})
        return b,c

    def test_all_static_failures_collected_and_prevent_start(self):
        b,c=self.fixture();c['Image']='wrong';c['Config']['Env']=['A=changed'];c['Mounts'][0]['RW']=False
        codes=identity_mismatches(b,c,True)
        self.assertEqual(set(codes),{'worker_image_mismatch','environment_mapping_mismatch','mount_mode_mismatch'})
        start=Mock()
        with self.assertRaises(ValueError):start_verified(b,c,start)
        start.assert_not_called()

    def test_exact_created_id_started_once_and_post_start_drift_detected(self):
        b,c=self.fixture();start=Mock();start_verified(b,c,start);start.assert_called_once_with('exact-id')
        c['State'].update(Running=True,Status='running',StartedAt='2026-11-18T00:00:00Z')
        self.assertEqual(identity_mismatches(b,c),[])
        with self.assertRaises(ValueError):start_verified(b,c,Mock())
        c['Config']['Cmd']=['wrong'];self.assertIn('worker_command_mismatch',identity_mismatches(b,c))

    def test_content_failure_not_hidden_by_allowed_mount_and_other_mismatch(self):
        b,c=self.fixture();c['Mounts'][0]['RW']=False
        with self.assertRaisesRegex(AssertionError,'mounted_content_mismatch'):
            verify_mounts(b['mount_spec'],c['Mounts'],Mock(side_effect=ValueError('controlled')))

if __name__=='__main__':unittest.main()
