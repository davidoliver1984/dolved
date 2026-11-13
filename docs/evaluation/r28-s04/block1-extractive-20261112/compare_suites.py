"""Offline attribution only; no test reruns or provider calls."""
import hashlib
import json
from pathlib import Path
import subprocess
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[4]
out = Path(__file__).parent
def read(name):
    cases = list(ET.parse(out/'verification'/name).iter('testcase'))
    failures = {}
    for case in cases:
        error = case.find('failure')
        if error is None:
            error = case.find('error')
        if error is not None:
            failures[case.get('classname')+'::'+case.get('name')] = {
                'message':error.get('message'), 'detail':error.text}
    return {'tests':len(cases), 'skipped':sum(c.find('skipped') is not None for c in cases),
            'failures':failures}
result = {'baseline_commit':'348f09e053c8ad73676921c5c616cced812111d7','suites':{}}
for suite in ['ai','api']:
    a,b=read(f'baseline-{suite}.xml'),read(f'changed-{suite}.xml')
    result['suites'][suite] = {'baseline':a,'changed':b,
        'new_failures':sorted(b['failures'].keys()-a['failures'].keys()),
        'resolved_failures':sorted(a['failures'].keys()-b['failures'].keys())}
(out/'suite-comparison.json').write_text(json.dumps(result,indent=2)+'\n')
paths=subprocess.check_output(['git','ls-files','-z','--','apps/api/app','apps/api/config',
    'apps/api/routes','apps/ai/app','contracts'],cwd=root).split(b'\0')
files={p.decode() for p in paths if p}
files.update(str(p.relative_to(root)) for d in ['contracts/http/retrieval-needs/v4','contracts/http/generation-needs/v3'] for p in (root/d).rglob('*') if p.is_file())
hashes={p:hashlib.sha256((root/p).read_bytes()).hexdigest() for p in sorted(files)}
aggregate=hashlib.sha256(''.join(f'{h}  {p}\n' for p,h in hashes.items()).encode()).hexdigest()
(out/'candidate-source-freeze.json').write_text(json.dumps({'aggregate_including_new_contracts':aggregate,
    'files':hashes,'provider_calls':0,'live_authorization_consumed':False},indent=2)+'\n')
for s,v in result['suites'].items():
    print(s,len(v['baseline']['failures']),len(v['changed']['failures']),v['new_failures'])
