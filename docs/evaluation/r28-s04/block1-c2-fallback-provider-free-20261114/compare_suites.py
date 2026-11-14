"""Exact failure identities; no execution, no provider dependencies."""
import hashlib
import json
from pathlib import Path
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parent
baseline = root.parent / 'block1-extractive-20261112/verification'
result = {}
for suite in ('ai', 'api'):
    rows = []
    for path in (baseline / f'migrated-ready-{suite}.xml', root / ('changed-api-complete.xml' if suite == 'api' else 'changed-ai-final.xml')):
        cases = list(ET.parse(path).iter('testcase'))
        failed = sorted(f"{case.get('classname')}::{case.get('name')}" for case in cases
                        if case.find('failure') is not None or case.find('error') is not None)
        rows.append({'path': str(path.relative_to(root.parent)), 'sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
                     'tests': len(cases), 'failures': len(failed), 'failure_names': failed})
    old, new = rows
    result[suite] = {'baseline': old, 'changed': new,
                     'new': sorted(set(new['failure_names']) - set(old['failure_names'])),
                     'resolved': sorted(set(old['failure_names']) - set(new['failure_names']))}
print(json.dumps(result, indent=2))
