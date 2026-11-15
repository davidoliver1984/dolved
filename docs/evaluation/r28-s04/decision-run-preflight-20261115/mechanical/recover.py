"""Recover running service configuration from stdin; secrets ONLY in private mount.

Never prints the inspected environment or writes it to evaluation evidence.
"""
import hashlib
import json
import os
import sys
from pathlib import Path

containers=json.load(sys.stdin)
services={}; safe={}
for c in containers:
    service=c['Config']['Labels']['com.docker.compose.service']
    assert c['Config']['Labels']['com.docker.compose.project']=='dolved-r28-s03'
    assert service in ('api','ai') and c['State']['Running']
    env=dict(v.split('=',1) for v in c['Config']['Env'])
    if service=='api':assert env['DB_DATABASE']=='dolved_e2e_r28_s03' and env['DB_HOST']=='postgres'
    # Compose interpolation must not alter secret or non-secret dollar characters.
    services[service]={'image':c['Image'],'environment':{k:v.replace('$','$$') for k,v in env.items()},
        'command':c['Config']['Cmd'],'entrypoint':c['Config']['Entrypoint'],
        'working_dir':c['Config']['WorkingDir'],'user':c['Config']['User']}
    allowed=('PROVIDER','MODEL','MAX_ATTEMPTS','MAX_OUTPUT_TOKENS','TIMEOUT_SECONDS','REASONING_EFFORT','CONTEXT_WINDOW_TOKENS')
    safe[service]={'image':c['Image'],'identities':{k:v for k,v in env.items() if k.endswith(allowed)},
        'credential_presence':{k:bool(v) for k,v in env.items() if k in ('OPENAI_API_KEY','RETRIEVAL_PLANNER_API_KEY','GENERATION_OPENAI_API_KEY','VOYAGE_API_KEY')},
        'project':'dolved-r28-s03'}
assert set(services)=={'api','ai'}
destination=Path('/private-config/production.override.json')
if destination.exists():raise SystemExit('Refuse overwrite of recovered private configuration')
content=(json.dumps({'services':services},indent=2)+'\n').encode()
fd=os.open(destination,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
with os.fdopen(fd,'wb') as f:f.write(content);f.flush();os.fsync(f.fileno())
assert destination.stat().st_mode&0o777==0o600
print(json.dumps({'recovered':True,'override_sha256':hashlib.sha256(content).hexdigest(),'services':safe,'secrets_in_evidence':False},indent=2))
