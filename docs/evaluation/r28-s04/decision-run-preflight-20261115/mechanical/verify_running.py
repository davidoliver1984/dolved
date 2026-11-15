"""Inspect actual deployment without retaining environment values or credentials."""
import json, sys
from pathlib import Path
expected=json.loads(Path('/private-config/production.override.json').read_text())['services']
out={}
for container in json.load(sys.stdin):
    cfg=container['Config']; labels=cfg['Labels']; name=labels['com.docker.compose.service']
    assert labels['com.docker.compose.project']=='dolved-r28-s03' and name in expected
    assert container['State']['Running'] and container['State']['Health']['Status']=='healthy'
    values={k:v.replace('$$','$') for k,v in expected[name]['environment'].items()}
    if name=='ai':values.update(EVALUATION_CAPTURE_ENABLED='true',EVALUATION_CAPTURE_DESTINATION='/evaluation-evidence/capture')
    actual=dict(v.split('=',1) for v in cfg['Env'])
    assert actual==values, 'environment_mismatch'
    assert container['Image']==expected[name]['image']
    for key,old in [('Cmd','command'),('Entrypoint','entrypoint'),('User','user'),('WorkingDir','working_dir')]:
        assert cfg[key]==expected[name][old],key
    if name=='ai':
        assert any(m['Destination']=='/evaluation-evidence' and m['Source'].removeprefix('/host_mnt')=='/Users/davidoliver/Desktop/RAG/docs/evaluation/r28-s04/decision-run-20261115/backend-run' and m['RW'] for m in container['Mounts'])
    out[name]={'healthy':True,'all_environment_values_verified':True,'image_command_user_verified':True,'container_id':container['Id']}
assert set(out)=={'api','ai'}
print(json.dumps(out,indent=2))
