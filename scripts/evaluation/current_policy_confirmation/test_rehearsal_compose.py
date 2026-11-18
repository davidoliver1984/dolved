import copy
import json
from pathlib import Path
import tempfile
import unittest
from rehearsal_compose import private_snapshot,verify_resolved


class RehearsalComposeTests(unittest.TestCase):
    def test_private_snapshot_preserves_literal_interpolation_and_permissions(self):
        with tempfile.TemporaryDirectory() as t:
            p=Path(t)/'resolved.json';private_snapshot({'services':{'api':{'environment':{'SYNTHETIC':'a=$x==','EMPTY':''}}}},p)
            self.assertEqual(p.stat().st_mode&0o777,0o600)
            self.assertEqual(json.loads(p.read_text())['services']['api']['environment']['SYNTHETIC'],'a=$$x==')
            with self.assertRaises(FileExistsError):private_snapshot({},p)

    def test_resolved_dependencies_ports_and_unrelated_changes_refused(self):
        base={'services':{'api':{'image':'pinned','environment':{'SYNTHETIC':'value'},'volumes':[],'networks':{'default':None}}}}
        row={**base['services']['api'],'command':['rehearsal']}
        verify_resolved({'services':{'test':row}},base,'test',['rehearsal'],{})
        for key,value in [('depends_on',{'api':{}}),('links',['api:alias']),('volumes_from',['api']),('network_mode','service:api'),('pid','container:any'),('ports',[{'published':'1234'}]),('environment',{}),('networks',{})]:
            with self.subTest(key=key):
                changed=copy.deepcopy(row);changed[key]=value
                with self.assertRaises(ValueError):verify_resolved({'services':{'test':changed}},base,'test',['rehearsal'],{})

if __name__=='__main__':unittest.main()
