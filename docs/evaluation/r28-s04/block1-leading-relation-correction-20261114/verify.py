"""Freeze runtime and compare retained baseline JUnit failure identities."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path('/workspace')
HERE = Path(__file__).resolve().parent
BASE = json.loads((HERE.parent / 'block1-temporal-association-diagnostic-20261114/provider-free/suite-comparison.json').read_text())
comparison = {}
for service in ('ai', 'api'):
    path = HERE / f'{service}-full-final.xml'
    suite = ET.fromstring(path.read_bytes())
    cases = list(suite.iter('testcase'))
    failures = sorted({f"{c.get('classname')}::{c.get('name')}" for c in cases if c.find('failure') is not None or c.find('error') is not None})
    baseline = BASE[service]['baseline']
    comparison[service] = {
        'baseline': baseline,
        'current': {'path': str(path.relative_to(ROOT)), 'sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
                    'tests': len(cases), 'failures': failures,
                    'skipped': sum(c.find('skipped') is not None for c in cases)},
        'new': sorted(set(failures) - set(baseline['failures'])),
        'not_reproduced': sorted(set(baseline['failures']) - set(failures)),
    }
(HERE / 'suite-comparison.json').write_text(json.dumps(comparison, indent=2) + '\n')
print(json.dumps({s: {k: v for k,v in r.items() if k in ('new','not_reproduced')} for s,r in comparison.items()}, indent=2))
paths = subprocess.check_output(['git', '-c', 'safe.directory=/workspace', 'ls-files', '--', 'apps/api/app', 'apps/api/config', 'apps/api/routes', 'apps/ai/app', 'contracts'], cwd=ROOT).decode().splitlines()
manifest = ''.join(f'{hashlib.sha256((ROOT/p).read_bytes()).hexdigest()}  {p}\n' for p in sorted(paths))
(HERE / 'runtime-source.sha256').write_text(manifest)
(HERE / 'runtime-identity.json').write_text(json.dumps({'aggregate': hashlib.sha256(manifest.encode()).hexdigest(), 'files': len(paths), 'planner_adapter': 'structured-chat-needs-v12', 'prompt': 'bounded-evidence-needs-v26', 'schema': 'evidence-needs-plan-v4'}, indent=2) + '\n')
