"""Provider-free evidence sealing; no provider, deployment or corpus access."""
import ast
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

import tiktoken

ROOT = Path('/workspace')
HERE = Path(__file__).resolve().parent
baseline = json.loads((HERE.parent / 'source-review-receipt-20261115/suite-comparison.json').read_text())
comparison = {}
for service, filename in [('ai', 'ai-final.xml'), ('api', 'api-release.xml')]:
    path = HERE / filename
    cases = list(ET.fromstring(path.read_bytes()).iter('testcase'))
    failures = sorted({f"{c.get('classname')}::{c.get('name')}" for c in cases
                       if c.find('failure') is not None or c.find('error') is not None})
    old = baseline[service]['failures']
    comparison[service] = {'tests': len(cases), 'skipped': sum(c.find('skipped') is not None for c in cases),
        'failures': failures, 'baseline_failures': old, 'new': sorted(set(failures)-set(old)),
        'not_reproduced': sorted(set(old)-set(failures)), 'junit_sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
(HERE/'suite-comparison.json').write_text(json.dumps(comparison, indent=2)+'\n')
def prompt(source):
    return next(ast.literal_eval(node.value) for node in ast.parse(source).body
                if isinstance(node, ast.Assign) and any(isinstance(t, ast.Name) and t.id == 'PROMPT' for t in node.targets))
old_source = subprocess.check_output(['git', '-c', 'safe.directory=/workspace', 'show',
    '72a83ab7d0565e73aa83a18fa47a7afb6b293038:apps/ai/app/retrieval/needs_planner.py'], cwd=ROOT).decode()
new_source = (ROOT/'apps/ai/app/retrieval/needs_planner.py').read_text()
encode = tiktoken.get_encoding('o200k_base').encode
old_tokens, new_tokens = len(encode(prompt(old_source))), len(encode(prompt(new_source)))
(HERE/'prompt-sizing.json').write_text(json.dumps({'measurement': 'provider-free canonical prompt text, not provider usage',
    'old_prompt_tokens': old_tokens, 'new_prompt_tokens': new_tokens,
    'delta_per_planner_attempt': new_tokens-old_tokens,
    'two_attempt_delta': 2*(new_tokens-old_tokens), 'new_calls': 0,
    'production_output_limits_unchanged': True,
    'reservation': 'Recompute complete decision-run reservation at preflight with prompt v27; retain the previous review/selector bounds and existing retry accounting. No authorisation consumed.'}, indent=2)+'\n')
paths = set(subprocess.check_output(['git', '-c', 'safe.directory=/workspace', 'ls-files', '--',
    'apps/api/app', 'apps/api/config', 'apps/api/routes', 'apps/ai/app', 'contracts'], cwd=ROOT).decode().splitlines())
paths.add('contracts/http/retrieval-needs/v4/temporal-meaning-examples.json')
manifest = ''.join(f'{hashlib.sha256((ROOT/p).read_bytes()).hexdigest()}  {p}\n' for p in sorted(paths))
(HERE/'runtime-source.sha256').write_text(manifest)
(HERE/'runtime-identity.json').write_text(json.dumps({
    'starting_commit': '72a83ab7d0565e73aa83a18fa47a7afb6b293038',
    'aggregate': hashlib.sha256(manifest.encode()).hexdigest(), 'files': len(paths),
    'planner_prompt': 'bounded-evidence-needs-v27', 'planner_adapter': 'structured-chat-needs-v12',
    'planner_contract': 'evidence-needs-plan-v4', 'execution': 'bounded-needs-slice-v24',
    'controlled_renderer': 'controlled-conversation-response-v2',
    'generation_unchanged': 'v6 / grounded-generation-needs-v15 / openai-responses-needs-v7',
    'source_review_unchanged': 'need-source-support-v2 / 17408 tokens',
    'provider_calls': 0, 'live_confirmation': 'not performed'}, indent=2)+'\n')
sources = [
    HERE.parent/'run/cases/19.json', HERE.parent/'run/correctness-review.json',
    HERE.parent.parent/'block1-temporal-association-diagnostic-20261114/inputs/G3.json',
    HERE.parent.parent/'block1-temporal-association-diagnostic-20261114/capture/51b13294-d548-4241-b2cb-832eb5d6c162-b89ae43c-46f5-4cc0-8887-2b3c65da5932.json',
    HERE.parent.parent/'block1-temporal-association-diagnostic-20261114/capture/51b13294-d548-4241-b2cb-832eb5d6c162-de0a47b7-0731-44bd-b4a0-434f0ebb8d62.json',
]
(HERE/'retained-inputs.sha256').write_text(''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(ROOT)}\n' for p in sources))
(HERE/'checksums.sha256').write_text(''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.name}\n' for p in sorted(HERE.iterdir()) if p.is_file() and p.name != 'checksums.sha256'))
print(json.dumps({service: {k:v for k,v in result.items() if k in ('tests','skipped','new','not_reproduced')}
                  for service,result in comparison.items()}, indent=2))
