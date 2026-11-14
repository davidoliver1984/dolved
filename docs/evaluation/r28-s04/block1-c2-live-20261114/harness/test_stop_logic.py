import copy
import unittest
from check_record import disposition

class StopLogic(unittest.TestCase):
    def test_typed_invalid_review_is_recorded_not_infrastructure(self):
        r = {'status':'failed','service_calls':[{'status':'received','http_status':503,'path':'/api/internal/retrieval/compare-family','comparison_review_failure':'invalid_output'}],
             'retrieval':{'lineage':{'failure':{'category':'contract_validation_error','stage':'transport_orchestration','execution':'provider_api','provider':'openai'}}}}
        self.assertEqual(disposition(r)[0], 'continue')
        for code in ('authentication_failed','service_unavailable','unknown',None):
            changed = copy.deepcopy(r)
            changed['service_calls'][0]['comparison_review_failure'] = code
            self.assertEqual(disposition(changed)[0], 'stop')
        changed = copy.deepcopy(r)
        changed['retrieval']['lineage']['failure']['stage']='final_eligibility'
        self.assertEqual(disposition(changed)[0], 'stop')

    def test_safe_outcomes_and_unknown_failure(self):
        for status in ('completed','clarification_required','retrieval_no_answer'):
            self.assertEqual(disposition({'status':status})[0], 'continue')
        self.assertEqual(disposition({'status':'failed','failure_code':'retrieval_failed'})[0], 'stop')
        self.assertEqual(disposition({'status':'retrieval_no_answer','retrieval':{'clarification_reason':'deadline_exhausted'}})[0], 'stop')
        self.assertEqual(disposition({'status':'completed','service_calls':[{'status':'connection_failed'}]})[0], 'stop')

    def test_planner_rejection_requires_successful_provider_and_not_systemic(self):
        r={'status':'failed','usage':{'planner_failure':{'category':'invalid_grounded_needs_plan','provider_status':200,'systemic':False}}}
        self.assertEqual(disposition(r)[0], 'continue')
        r['usage']['planner_failure']['systemic']=True
        self.assertEqual(disposition(r)[0], 'stop')

if __name__ == '__main__': unittest.main()
