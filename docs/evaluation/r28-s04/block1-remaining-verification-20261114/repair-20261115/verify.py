"""Compare exact retained failure identities and freeze only scoped runtime files."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path('/workspace')
HERE = Path(__file__).resolve().parent
BASE_PATH = HERE.parents[1] / 'block1-leading-relation-correction-20261114/suite-comparison.json'
base = json.loads(BASE_PATH.read_text())
comparison = {}
for service in ('ai', 'api'):
    path = HERE / f'{service}-complete.xml'
    cases = list(ET.fromstring(path.read_bytes()).iter('testcase'))
    failures = sorted({f"{c.get('classname')}::{c.get('name')}" for c in cases
                       if c.find('failure') is not None or c.find('error') is not None})
    old = base[service]['baseline']['failures']
    comparison[service] = {'tests': len(cases), 'failures': failures,
        'skipped': sum(c.find('skipped') is not None for c in cases),
        'new': sorted(set(failures) - set(old)), 'not_reproduced': sorted(set(old) - set(failures)),
        'baseline': base[service]['baseline'], 'junit_sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
(HERE / 'suite-comparison.json').write_text(json.dumps(comparison, indent=2) + '\n')
paths = set(subprocess.check_output(['git', '-c', 'safe.directory=/workspace', 'ls-files', '--',
    'apps/api/app', 'apps/api/config', 'apps/api/routes', 'apps/ai/app', 'contracts'], cwd=ROOT).decode().splitlines())
paths.update(['apps/ai/app/generation/support_review.py', 'contracts/testing/generation-source-support-v1.json'])
manifest = ''.join(f'{hashlib.sha256((ROOT/p).read_bytes()).hexdigest()}  {p}\n' for p in sorted(paths))
(HERE / 'runtime-source.sha256').write_text(manifest)
(HERE / 'runtime-identity.json').write_text(json.dumps({'aggregate': hashlib.sha256(manifest.encode()).hexdigest(),
    'files': len(paths), 'execution': 'bounded-needs-slice-v21', 'support_contract': 'need-source-support-v1'}, indent=2) + '\n')
traces = []
for item, need_id, chunk_id in (
    (26, 'n1', 'c172d391-404f-524f-b645-00deeb087486'),
    (26, 'n3', '9aa4ac30-490d-5237-93ba-271c1f07f55a'),
    (28, 'n3', 'afd325b1-df26-51f5-bbc2-fe2e69b23c1c'),
):
    path = HERE.parent / f'run/cases/{item}.json'
    raw = json.loads(path.read_text())
    trace = raw['production_trace']
    passage = next(c for key, value in trace.items() if key.endswith('.hydrated')
                   for c in value if c['chunk_id'] == chunk_id)
    final = trace[f'need.{need_id}.final_evidence']
    ranked = trace[f'need.{need_id}.reranked']['candidates']
    handles = [e['evidence_id'] for e in raw['generation_request']['evidence']
               if e['text'] == passage['chunk_text']]
    traces.append({'item': item, 'need_id': need_id, 'chunk_id': chunk_id,
        'source_sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
        'document_id': passage['document_id'], 'family_id': passage['document_family_id'],
        'version_position': passage['version_position'], 'source_text': passage['chunk_text'],
        'document_in_need_eligibility': passage['document_id'] in trace[f'need.{need_id}.eligibility']['document_public_ids_by_side']['primary'],
        'need_reranked': [c for c in ranked if c['chunk_id'] == chunk_id],
        'need_final_rank': [c['rank'] for c in final if c['chunk_id'] == chunk_id],
        'generation_handles': handles,
        'generation_need_owners': [n['need_id'] for n in raw['generation_request']['evidence_needs']
                                   if set(n['evidence_ids']) & set(handles)]})
(HERE / 'allocation-traces.json').write_text(json.dumps(traces, indent=2) + '\n')
checksum_paths = sorted(p for p in HERE.iterdir() if p.is_file() and p.name != 'checksums.sha256')
(HERE / 'checksums.sha256').write_text(''.join(
    f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.name}\n' for p in checksum_paths))
print(json.dumps({s: {k:v for k,v in r.items() if k in ('tests', 'new', 'not_reproduced')} for s,r in comparison.items()}, indent=2))
