import copy,unittest,json
from pathlib import Path
from check_record import disposition
class RecordedPlannerRejection(unittest.TestCase):
    def test_retained_g2_is_safe_item_rejection(self):
        r=json.loads((Path(__file__).parent.parent/'cases/G2.json').read_text())
        self.assertEqual(disposition(r)[0],'continue')
        for change in ('systemic','status','category','outer_auth','finish'):
            x=copy.deepcopy(r)
            if change=='systemic': x['usage']['planner_failure']['systemic']=True
            if change=='status': x['usage']['planner_failure']['provider_status']=503
            if change=='category': x['usage']['planner_failure']['category']='provider_unavailable'
            if change=='outer_auth': x['service_calls'][0]['http_status']=401
            if change=='finish': x['usage']['planner_failure']['completion']['finish_reason']='unknown'
            self.assertEqual(disposition(x)[0],'stop',change)
    def test_shape_without_reviewer_data(self):
        r={'status':'retrieval_no_answer','service_calls':[{'path':'/api/internal/retrieval/plan-needs','status':'received','http_status':503}],
           'usage':{'planner_failure':{'category':'invalid_grounded_needs_plan','provider_status':200,'systemic':False,'completion':{'finish_reason':'stop'}}}}
        self.assertEqual(disposition(r)[0],'continue')
        del r['usage']
        self.assertEqual(disposition(r)[0],'stop')
if __name__=='__main__': unittest.main()
