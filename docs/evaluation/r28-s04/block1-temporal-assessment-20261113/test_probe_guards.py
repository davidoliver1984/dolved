"""Provider-free guards for this fixed experiment wrapper."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

SPEC = importlib.util.spec_from_file_location('temporal_probe_run', Path(__file__).with_name('run_probes.py'))
run = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(run)


class Guards(unittest.TestCase):
    def test_runtime_or_input_mismatch_refused_before_network(self):
        with patch.object(run.g, 'git', return_value=run.BRANCH), patch.object(run, 'freeze', return_value={'head':'different'}):
            with self.assertRaisesRegex(RuntimeError, 'identity changed'):
                run.check_pin({'head':'pinned'})

    def test_remote_sha_mismatch_refused(self):
        with patch.object(run, 'freeze', return_value={'head':'pinned'}), patch.object(run.g, 'git', side_effect=[run.BRANCH, '', 'other refs/heads/hold']):
            with self.assertRaisesRegex(RuntimeError, 'Remote hold SHA mismatch'):
                run.check_pin({'head':'pinned'})

    def test_consumed_authorization_cannot_call_provider_again(self):
        with tempfile.TemporaryDirectory() as directory:
            out = Path(directory)
            (out/'authorization.json').write_text(json.dumps({'runtime':{},'authorization_id':'test','reservation_usd':1.4}))
            (out/'consumed.json').write_text('{}')
            with patch.object(run, 'OUT', out), patch.object(run, 'check_pin'), patch.object(run.subprocess, 'run') as call:
                with self.assertRaises(FileExistsError):
                    run.execute()
                call.assert_not_called()


if __name__ == '__main__':
    unittest.main()
