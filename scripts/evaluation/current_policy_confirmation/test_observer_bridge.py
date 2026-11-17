"""Consume actual PHP hook journals, never historical smoke ID guesses."""
import json
import os
from pathlib import Path
import tempfile
import time
import unittest
from unittest.mock import patch

import native_capture as nc
import runner
from test_native_capture import uid


class ActualHookBridgeTest(unittest.TestCase):
    def test_actual_php_journal_to_reconciler(self):
        source = Path(os.environ['OBSERVER_HOOK_FIXTURES'])/'on'/'journal'
        entries = [(p.name,p.read_bytes(),json.loads(p.read_bytes())) for p in sorted(source.glob('*.json'))]
        self.assertTrue(entries)
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp)
            for name in ['journal','health','capture']:(root/name).mkdir()
            spec={n:str(root/n) for n in ['journal','health','capture']}
            spec.update(execution_id=uid(1),worker_id=uid(2),workspace_id=uid(3))
            r=nc.Reconciler(spec,root,runner.durable)
            (root/'journal'/entries[0][0]).write_bytes(entries[0][1])
            item=0;cap=0
            for name,raw,row in entries[1:]:
                if row['kind']=='worker_stop':break
                if row['kind']=='job_start':
                    item+=1;r.before(f'J{item}')
                (root/'journal'/name).write_bytes(raw)
                if row['kind']=='request_end' and row['transport']=='response':
                    attempts = row.get('attempts') or ([{'attempt':1,'status':'rejected'}] if row.get('generation_reason')=='invalid_output' else [])
                    for a in attempts:
                        cap+=1
                        record={'capture_version':'evaluation-attempt-v1','workspace_id':spec['workspace_id'],'request_id':row['request_id'],'invocation_id':uid(int(row['request_id'][-12:])+1000),'stage':row['stage'],'event_sequence':a['attempt'],**a}
                        if row['stage']=='current_policy_generation':record['validation']={'stage':'output_contract','field':'output','code':'structural_validation'}
                        (root/'capture'/f"{row['request_id']}-{uid(cap)}.json").write_text(json.dumps(record))
                if row['kind']=='job_end':
                    with patch('native_capture.time.sleep'):
                        if item==6:
                            with self.assertRaisesRegex(nc.CaptureError,'transport_failure'):
                                r.after(f'J{item}',row['run_id'],{'status':row['run_status']},time.time()+.2)
                        else:
                            r.after(f'J{item}',row['run_id'],{'status':row['run_status']},time.time()+.2)
            self.assertEqual(item,6)
            self.assertEqual(len(list(root.glob('*-native-attribution.json'))),5)


if __name__=='__main__':unittest.main()
