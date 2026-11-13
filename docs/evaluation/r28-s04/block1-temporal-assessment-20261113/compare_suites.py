"""Compare exact identities with the retained, migrated 38/2 baseline."""
import hashlib
import json
from pathlib import Path
import xml.etree.ElementTree as ET

OUT = Path(__file__).resolve().parent
OLD = OUT.parent / 'block1-extractive-20261112/verification'
result = {'suites': {}, 'junit_sha256': {}}
for suite in ('ai', 'api'):
    rows = []
    for p in (OLD / f'migrated-ready-{suite}.xml', OUT / f'verification/changed-{suite}.xml'):
        cases = list(ET.parse(p).iter('testcase'))
        failed = sorted(f"{c.get('classname')}::{c.get('name')}" for c in cases
                        if c.find('failure') is not None or c.find('error') is not None)
        rows.append({'tests': len(cases), 'failure_names': failed, 'failures': len(failed)})
        result['junit_sha256'][str(p.relative_to(OUT.parent))] = hashlib.sha256(p.read_bytes()).hexdigest()
    before, after = rows
    result['suites'][suite] = {'baseline': before, 'changed': after,
        'new': sorted(set(after['failure_names'])-set(before['failure_names'])),
        'resolved': sorted(set(before['failure_names'])-set(after['failure_names']))}
    print(suite, after['failures'], result['suites'][suite]['new'], result['suites'][suite]['resolved'])
with (OUT / 'suite-comparison.json').open('x') as handle:
    json.dump(result, handle, indent=2)
    handle.write('\n')
