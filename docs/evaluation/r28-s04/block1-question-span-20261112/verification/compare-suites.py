"""Compare exact raw JUnit failure-name sets; no providers."""
import json
from pathlib import Path
import xml.etree.ElementTree as ET

root = Path(__file__).parent


def read(path):
    cases = list(ET.parse(path).iter('testcase'))
    failures = sorted({c.attrib.get('classname', '') + '::' + c.attrib['name']
        for c in cases if c.find('failure') is not None or c.find('error') is not None})
    skipped = sum(c.find('skipped') is not None for c in cases)
    return {'tests': len(cases), 'failed': len(failures), 'skipped': skipped,
            'passed': len(cases)-len(failures)-skipped, 'failure_names': failures}


result = {'baseline_commit': '0f931126ec58bffa8f15d32540ad2395da57953c', 'suites': {}}
for service in ('ai', 'api'):
    before, after = read(root/f'baseline-{service}.xml'), read(root/f'changed-{service}.xml')
    result['suites'][service] = {'baseline': before, 'changed': after,
        'new_failures': sorted(set(after['failure_names'])-set(before['failure_names'])),
        'resolved_failures': sorted(set(before['failure_names'])-set(after['failure_names']))}
result['identical_failure_sets'] = all(not s['new_failures'] and not s['resolved_failures'] for s in result['suites'].values())
(root/'suite-comparison.json').write_text(json.dumps(result, indent=2)+'\n')
print(json.dumps(result, indent=2))
