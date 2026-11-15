import hashlib
import json
import unittest
from released_input import parse_release,execute_released

class Release(unittest.TestCase):
    def setUp(self):
        self.package={'schema_version':'decision-gate-questions-v1','fresh_answerable':[{'id':f'F{i}','group':'example','utterance':'  Faithful wording?\n'} for i in range(24)],
            'controls':[{'id':f'K{i}','control':'fixture','utterance':'Control?'} for i in range(4)],
            'known_regressions':[{'id':f'R{i}','source':'fixture','utterance':'Regression?'} for i in range(6)]}
    def parse(self):
        raw=json.dumps(self.package).encode()
        return parse_release(raw,hashlib.sha256(raw).hexdigest())
    def test_exact_order_wording_and_counts(self):
        rows=self.parse()
        self.assertEqual(len(rows),34)
        self.assertEqual([r['id'] for r in rows],[r['id'] for k in ('fresh_answerable','controls','known_regressions') for r in self.package[k]])
        self.assertEqual(rows[0]['utterance'],'  Faithful wording?\n')
        self.assertEqual([r['group'] for r in rows],['fresh']*24+['control']*4+['regression']*6)
        self.assertEqual(set(rows[-1]),{'id','utterance','group'})
    def test_changed_bytes_fail(self):
        with self.assertRaises(ValueError):parse_release(json.dumps(self.package).encode(),'0'*64)
    def test_duplicate_fails(self):
        self.package['controls'][0]['id']='F0'
        with self.assertRaises(ValueError):self.parse()
    def test_missing_item_fails(self):
        self.package['fresh_answerable'].pop()
        with self.assertRaises(ValueError):self.parse()
    def test_unapproved_release_never_reaches_existing_gate(self):
        with self.assertRaisesRegex(ValueError,'unapproved_release_identity'):
            execute_released(b'{}',{},None,None,None,None)
    def test_unconfirmed_context_never_reaches_existing_gate(self):
        from released_input import RELEASE_SHA256
        with self.assertRaisesRegex(ValueError,'reviewer_context_unconfirmed'):
            execute_released(b'{}',{'released_input_sha256':RELEASE_SHA256},None,None,None,None)

if __name__=='__main__':unittest.main()
