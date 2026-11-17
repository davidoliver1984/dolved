import json
import os
from pathlib import Path
import unittest

import native_capture as nc


class EmptyAttemptTests(unittest.TestCase):
    def test_actual_hook_empty_missing_and_recovered_outer_attempt_halt(self):
        root=Path(os.environ['EMPTY_ATTEMPT_FIXTURES'])
        for mode in ['empty','missing','retry']:
            with self.subTest(mode=mode):
                spec={'journal':str(root/mode/'journal'),'health':str(root/mode/'health'),
                      'execution_id':'00000000-0000-4000-8000-000000000001','worker_id':'00000000-0000-4000-8000-000000000002','workspace_id':'00000000-0000-4000-8000-000000000003'}
                _,jobs,requests,active,stopped=nc.journal(spec)
                self.assertIsNone(active);self.assertTrue(stopped)
                self.assertEqual(len(jobs),1)
                first=next(iter(requests.values()))['end']
                self.assertEqual(first['attempts'],None if mode=='missing' else [])
                with self.assertRaisesRegex(nc.CaptureError,'planner_boundary_or_receipts_unavailable'):
                    nc.reconcile_captures(spec,{},requests)
                if mode=='retry':self.assertEqual(list(requests.values())[1]['end']['attempts'],[{'attempt':1,'status':'accepted'}])

    def test_malformed_and_successful_empty_poison_observer(self):
        root=Path(os.environ['EMPTY_ATTEMPT_FIXTURES'])
        for mode in ['null','string','object','successful_empty']:
            with self.subTest(mode=mode):
                self.assertEqual((root/mode/'health'/'observer-failed').read_text().strip(),'observer_health_failure')


if __name__=='__main__':unittest.main()
