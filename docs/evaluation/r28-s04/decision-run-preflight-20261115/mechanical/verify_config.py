"""Compare effective Compose configuration without emitting any environment values."""
import json
import sys
from pathlib import Path
actual=json.load(sys.stdin)
expected=json.loads(Path('/private-config/production.override.json').read_text())['services']
assert actual['name']=='dolved-r28-s03'
out={}
for name in ('api','ai'):
    service=actual['services'][name]; old=expected[name]
    values={k:v.replace('$$','$') for k,v in old['environment'].items()}
    if name=='ai':values.update(EVALUATION_CAPTURE_ENABLED='true',EVALUATION_CAPTURE_DESTINATION='/evaluation-evidence/capture')
    mismatch=sorted(k for k in set(values)|set(service['environment']) if values.get(k)!=service['environment'].get(k))
    if mismatch:raise SystemExit('Environment mismatch keys: '+','.join(mismatch))
    for key in ('image','command','entrypoint','working_dir','user'):
        assert service.get(key)==old.get(key),name+':'+key
    ports=service['ports'];assert len(ports)==1 and ports[0]['host_ip']=='127.0.0.1'
    assert str(ports[0]['published'])==('8200' if name=='api' else '8201')
    out[name]={'all_environment_values_match':True,'image_command_user_match':True,'port_binding_match':True,
               'intentional_change':'capture enablement and evidence mount only' if name=='ai' else 'none'}
assert any(v['target']=='/evaluation-evidence' and v['type']=='bind' for v in actual['services']['ai']['volumes'])
print(json.dumps(out,indent=2))
