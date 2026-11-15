"""Provider-free destination smoke using existing capture API, not a planner run."""
import json
from pathlib import Path
from app.settings import get_settings
from app import evaluation_capture as c
s=get_settings()
assert s.evaluation_capture_enabled and s.evaluation_capture_destination=='/evaluation-evidence/capture'
destination=Path(s.evaluation_capture_destination)
request_id='00000000-0000-4000-8000-000000000001'
assert not list(destination.glob(request_id+'-*.json')), 'Do not duplicate destination proof'
with c.capture_scope(s,c.WORKSPACE,request_id,'phase_a_destination_smoke'):
    c.planner_begin(1)
    c.planner_proposal({'needs':[{'need_id':'development-fixture','operation':'retrieve'}]})
    c.calendar_normalized(0)
    c.planner_end('rejected',{'stage':'grounding','field':'fixture','code':'development_fixture'})
files=list(destination.glob(request_id+'-*.json'))
assert len(files)==1
record=json.loads(files[0].read_text())
assert record['workspace_id']==c.WORKSPACE and record['validation']['code']=='development_fixture'
with c.capture_scope(s,'be22830b-ddf1-4de5-b852-f7f4c111cf8e','00000000-0000-4000-8000-000000000002','phase_a_negative'):
    c.planner_begin(1);c.planner_end('accepted')
assert not list(destination.glob('00000000-0000-4000-8000-000000000002-*.json'))
print(json.dumps({'purpose':'provider_free_destination_only_not_validation_or_dispatch','file':files[0].name,
    'authorised_workspace_write':True,'foreign_workspace_refused':True,'provider_calls':0,'application_runs_created':0}))
