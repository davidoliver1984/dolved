import copy,json,unittest
from pathlib import Path
import httpx
from pydantic import SecretStr
from app.retrieval.needs import UserTurn
from app.retrieval.needs_planner import StructuredChatNeedsPlanner
from app.retrieval.planner import RetrievalPlanningError
from capture import Capture,WORKSPACE,attempts,project,PLAN

def fixture(kind):
    p=copy.deepcopy(json.loads(Path('/contracts/http/retrieval-needs/v4/examples.json').read_text())[0]['plan'])
    n=copy.deepcopy(p['needs'][1]); q='What checks were required in April 2025?'
    n.update(need_id='n1',aspect=q,question_span={'turn_id':'u1','quote':q},supports_need_id=None,context_spans=[])
    if kind=='fact_date_precision': n['fact_reference']={'precision':'day','value':'2025-04','source':{'turn_id':'u1','quote':'April 2025'}}
    if kind=='temporal_relation_grounding': n['authority']={'kind':'after','precision':'month','value':'2025-04','source':{'turn_id':'u1','quote':'April 2025'}}
    p['needs']=[n];p['bindings']=[copy.deepcopy(p['bindings'][0])]
    return p,q

def execute(outputs, enabled):
    cap=Capture(WORKSPACE); bodies=[]; index=0
    def handle(req):
        nonlocal index
        body=json.loads(req.content);bodies.append(body)
        kind=body['response_format']['json_schema']['name']
        if kind=='interpretation_check': content={'code':'accepted','citation':None}
        else: content=outputs[min(index,len(outputs)-1)];index+=1
        return httpx.Response(200,json={'choices':[{'finish_reason':'stop','message':{'content':json.dumps(content)}}],'usage':{'prompt_tokens':100,'completion_tokens':50}})
    with httpx.Client(transport=httpx.MockTransport(handle),event_hooks={'request':[cap.request],'response':[cap.response]} if enabled else {}) as client:
        planner=StructuredChatNeedsPlanner(api_url='https://not-dispatched.invalid',api_key=SecretStr('not-a-real-key'),provider_name='openai',model='gpt-5-mini',timeout_seconds=1,client=client)
        try:
            result=planner.plan_needs((UserTurn(turn_id='u1',text=fixture('valid')[1]),),evaluated_at='2026-11-14T12:00:00Z')
            outcome=('accepted',result.plan.model_dump(mode='json')); receipts=result.usage.validation_attempts
        except RetrievalPlanningError as e:
            outcome=('rejected',getattr(e,'validation',None),e.attempt_count);receipts=e.validation_attempts
    return outcome,bodies,attempts(cap,receipts),cap

class CaptureProof(unittest.TestCase):
    def test_rejections_are_identical_and_fields_retained(self):
        for code in ('fact_date_precision','temporal_relation_grounding'):
            with self.subTest(code=code):
                p,_=fixture(code)
                off=execute([p],False);on=execute([p],True)
                self.assertEqual(off[:2],on[:2]);self.assertEqual(len(on[2]),2)
                for a in on[2]:
                    self.assertEqual(a['validation']['code'],code)
                    self.assertEqual(a['proposed_plan'],project(p,PLAN))
                    self.assertEqual(a['status'],'rejected')
                self.assertEqual(on[1][1]['messages'][1]['content'],off[1][1]['messages'][1]['content'])
                proof=Path(__file__).resolve().parent.parent/'proof'/f'{code}.json'
                proof.write_text(json.dumps({'controlled_fixture':True,'no_providers':True,'capture_on_off_requests_and_outcome_identical':off[:2]==on[:2],'attempts':on[2]},indent=2)+'\n')
    def test_corrected_retry_and_first_acceptance_unchanged(self):
        good,_=fixture('valid');bad,_=fixture('fact_date_precision')
        for sequence in ([good],[bad,good]):
            off=execute(sequence,False);on=execute(sequence,True)
            self.assertEqual(off[:2],on[:2]);self.assertEqual(on[0][0],'accepted')
            self.assertEqual(on[2][-1]['status'],'accepted')
    def test_scope_and_redaction(self):
        with self.assertRaises(ValueError): Capture('foreign-workspace')
        p,_=fixture('valid');p['credentials']='DO-NOT-RETAIN';p['needs'][0]['aspect']='Bearer SECRET-TOKEN'
        value=project(p,PLAN);self.assertNotIn('DO-NOT-RETAIN',json.dumps(value));self.assertNotIn('SECRET-TOKEN',json.dumps(value))
        p['needs'][0]['question_span']['quote']='x'*9000
        self.assertEqual(len(project(p,PLAN)['needs'][0]['question_span']['quote']),8000)

if __name__=='__main__': unittest.main()
