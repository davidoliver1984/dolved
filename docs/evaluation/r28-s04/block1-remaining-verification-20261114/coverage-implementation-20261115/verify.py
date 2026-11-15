"""Provider-free failure identity comparison and bounded receipt sizing."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

import tiktoken

from app.generation.support_review import SUPPORT_REVIEW_OUTPUT_TOKENS, SourceSupportReview
from tests.test_generation_support_review import ANSWER, SOURCE

HERE = Path(__file__).resolve().parent
base = json.loads((HERE.parent / 'repair-20261115/suite-comparison.json').read_text())
results = {}
for service in ('ai', 'api'):
    path = HERE / ('api-accounting-complete.xml' if service == 'api' else 'ai-complete.xml')
    cases = list(ET.fromstring(path.read_bytes()).iter('testcase'))
    failures = sorted({f"{c.get('classname')}::{c.get('name')}" for c in cases
                       if c.find('failure') is not None or c.find('error') is not None})
    old = base[service]['failures']
    results[service] = {'tests': len(cases), 'failures': failures,
        'skipped': sum(c.find('skipped') is not None for c in cases),
        'baseline_failures': old, 'new': sorted(set(failures) - set(old)),
        'not_reproduced': sorted(set(old) - set(failures)),
        'junit_sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
(HERE / 'suite-comparison.json').write_text(json.dumps(results, indent=2) + '\n')
source = ((SOURCE + ' ') * 70)[:8000]
parts = [{'part_index': n-1, 'verdict': 'supported', 'missing_condition': None,
          'claims': [{'need_id': f'n{n}', 'answer_quote': ANSWER * 3,
                      'evidence_id': f'ev-{e:02}', 'source_quote': source,
                      'same_situation': 'established', 'requirement_preserved': 'established'}
                     for e in range(1, 6)]} for n in range(1, 4)]
receipt = SourceSupportReview.model_validate({'parts': parts})
tokens = len(tiktoken.get_encoding('o200k_base').encode(receipt.model_dump_json()))
sizing = {'measurement': 'local tokenization, no provider call', 'claims': 15,
          'needs': 3, 'physical_sources': 5, 'source_quote_characters': len(source),
          'receipt_output_tokens': tokens, 'reasoning_allowance': 4096,
          'old_ceiling': 1024, 'new_ceiling': SUPPORT_REVIEW_OUTPUT_TOKENS,
          'fits_with_allowance': tokens + 4096 <= SUPPORT_REVIEW_OUTPUT_TOKENS,
          'additional_reserved_output_per_review': SUPPORT_REVIEW_OUTPUT_TOKENS - 1024,
          'additional_reserved_output_two_generation_attempts': 2 * (SUPPORT_REVIEW_OUTPUT_TOKENS - 1024),
          'not_a_guarantee': 'Larger pathological schema-valid receipts can still exhaust the bound; incomplete output is rejected, not consumed.'}
(HERE / 'receipt-sizing.json').write_text(json.dumps(sizing, indent=2) + '\n')
print(json.dumps({'suites': {s: {k:v for k,v in r.items() if k in ('tests','new','not_reproduced')} for s,r in results.items()}, 'sizing': sizing}, indent=2))
root = Path('/workspace')
paths = set(subprocess.check_output(['git', '-c', 'safe.directory=/workspace', 'ls-files', '--',
    'apps/api/app', 'apps/api/config', 'apps/api/routes', 'apps/ai/app', 'contracts'], cwd=root).decode().splitlines())
paths.update(['apps/ai/app/retrieval/coverage_selection.py',
    'apps/api/app/Exceptions/CoverageSelectionFailure.php',
    'apps/api/app/Services/Retrieval/CoverageSelectionClient.php',
    'apps/api/app/Support/Retrieval/CoverageAllocation.php'])
for folder in ('contracts/http/generation-needs/v6', 'contracts/http/retrieval-coverage-selection/v1'):
    paths.update(str(p.relative_to(root)) for p in (root/folder).rglob('*') if p.is_file())
manifest = ''.join(f'{hashlib.sha256((root/p).read_bytes()).hexdigest()}  {p}\n' for p in sorted(paths))
(HERE/'runtime-source.sha256').write_text(manifest)
(HERE/'runtime-identity.json').write_text(json.dumps({'aggregate': hashlib.sha256(manifest.encode()).hexdigest(),
    'files': len(paths), 'execution': 'bounded-needs-slice-v22', 'coverage': 'grounded-coverage-selection-v1',
    'generation_contract': 6, 'support_review_output_tokens': SUPPORT_REVIEW_OUTPUT_TOKENS,
    'live_execution': False}, indent=2)+'\n')
(HERE/'checksums.sha256').write_text(''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.name}\n'
    for p in sorted(HERE.iterdir()) if p.is_file() and p.name != 'checksums.sha256'))
