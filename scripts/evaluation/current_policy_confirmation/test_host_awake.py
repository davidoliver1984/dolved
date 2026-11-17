import hashlib
import json
from pathlib import Path
import unittest
from unittest.mock import patch

import host_awake


class HostAwakeTests(unittest.TestCase):
    def binding(self):
        return {'binding_status': 'EXECUTION_RELEASE', 'host_awake': {
            'command': ['/bound/python', '/bound/commands.py', 'execute'],
            'files': {p: hashlib.sha256(b'fixture').hexdigest() for p in
                      ['/usr/bin/caffeinate', '/bound/python', '/bound/commands.py']}}}

    def test_exact_utility_lifetime_command_no_retry(self):
        with patch.object(Path, 'read_bytes', return_value=b'fixture'):
            self.assertEqual(host_awake.command(self.binding()),
                             ['/usr/bin/caffeinate', '-i', '-s', '/bound/python', '/bound/commands.py', 'execute'])
        with patch.object(Path, 'read_text', return_value=json.dumps(self.binding())), \
                patch.object(Path, 'read_bytes', return_value=b'fixture'), \
                patch('host_awake.os.execv', side_effect=OSError('controlled')) as execute:
            with self.assertRaises(OSError):
                host_awake.main('synthetic-binding')
            execute.assert_called_once()

    def test_draft_refused(self):
        b = self.binding(); b['binding_status'] = 'DRAFT_PREPARATION_ONLY_NOT_EXECUTION_RELEASE'
        with self.assertRaisesRegex(ValueError, 'final release'):
            host_awake.command(b)

    def test_drift_missing_dependencies_and_other_command_refused(self):
        with patch.object(Path, 'read_bytes', return_value=b'changed'):
            with self.assertRaisesRegex(ValueError, 'drift'):
                host_awake.command(self.binding())
        b = self.binding(); del b['host_awake']['files']['/bound/python']
        with self.assertRaisesRegex(ValueError, 'incomplete'):
            host_awake.command(b)
        b = self.binding(); b['host_awake']['command'][2] = 'worker-start'
        with self.assertRaisesRegex(ValueError, 'unbound'):
            host_awake.command(b)


if __name__ == '__main__':
    unittest.main()
