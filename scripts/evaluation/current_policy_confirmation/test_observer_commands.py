import copy
import unittest
import observer_commands as c


class ObservedWorkerConfigurationTests(unittest.TestCase):
    def test_environment_exact_mapping(self):
        c.verify_environment(['A=1','B=','C=a=b'],['C=a=b','B=','A=1'])
        for entries in [['A=2','B=','C=a=b'],['A=1','B='],['A=1','B=','C=a=b','D='],['A=1','A=1'],['bad'],['=value'],[None]]:
            with self.subTest(entries=entries),self.assertRaises(ValueError):c.verify_environment(['A=1','B=','C=a=b'],entries)
    def fixture(self):
        base={'image':'pinned','environment':{'CONTROLLED':'yes'},'command':['original'],'volumes':[{'type':'bind','source':'/approved/app','target':'/app'}]}
        mounts={t:{'source':'/approved/'+str(i),'read_only':i<2,'allowed_source_spellings':['/approved/'+str(i),'/host_mnt/approved/'+str(i)]} for i,t in enumerate(['/evaluation-observer','/evaluation-observer-config/observer.json','/observer-journal','/observer-health'])}
        observed={**base,'command':['php','/evaluation-observer/observed_worker.php','/evaluation-observer-config/observer.json'],'volumes':base['volumes']+[{'type':'bind','source':m['source'],'target':t,'read_only':m['read_only']} for t,m in mounts.items()]}
        return base,observed,mounts

    def test_exact_observed_queue_entrypoint_and_mount_set(self):
        b,o,m=self.fixture();c.validate_observer_service(b,o,m)
        for mode in ['queue','environment','readonly','extra']:
            with self.subTest(mode=mode):
                changed=copy.deepcopy(o)
                if mode=='queue':changed['command']=['php','artisan','queue:work','--queue=document-governance']
                elif mode=='environment':changed['environment']['OTHER']='changed'
                elif mode=='readonly':changed['volumes'][1]['read_only']=False
                else:changed['volumes'].append({'target':'/unbound','source':'/elsewhere','type':'bind'})
                with self.assertRaises(AssertionError):c.validate_observer_service(b,changed,m)

    def test_started_mounts_exact_bound_spellings_only(self):
        _,_,extra=self.fixture();base=[{'Destination':'/app','Type':'bind','Source':'/approved/app','RW':True}]
        worker=base+[{'Destination':t,'Type':'bind','Source':v['allowed_source_spellings'][1],'RW':not v['read_only']} for t,v in extra.items()]
        spec={'/app':{'Type':'bind','RW':True,'allowed_sources':['/approved/app']},**{t:{'Type':'bind','RW':not v['read_only'],'allowed_sources':v['allowed_source_spellings']} for t,v in extra.items()}}
        c.verify_started_mounts(spec,worker)
        for change in ['source','mode','destination']:
            x=copy.deepcopy(worker)
            if change=='source':x[-1]['Source']='/other/approved/3'
            elif change=='mode':x[-1]['RW']=not x[-1]['RW']
            else:x[-1]['Destination']='/wrong'
            with self.assertRaises(AssertionError):c.verify_started_mounts(spec,x)


if __name__=='__main__':unittest.main()
