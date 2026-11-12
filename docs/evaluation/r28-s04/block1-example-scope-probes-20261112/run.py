"""Bounded evidence-only invocation of the existing omission-probe transport."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).parent
COMPOSE = ['docker', 'compose', '--env-file', '.env.r28-s03', '-p', 'dolved-r28-s03',
           '-f', 'compose.yaml', '-f', 'compose.e2e.yaml', '-f', 'compose.r28-s03.yaml']
RUNTIME = ['apps/api/app', 'apps/api/config', 'apps/api/routes', 'apps/ai/app', 'contracts']
def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT).decode().strip()
def digest(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()
def write(name, value):
    with (OUT/name).open('x') as f:
        json.dump(value, f, indent=2); f.write('\n')
def freeze():
    files = subprocess.check_output(['git', 'ls-files', '-z', '--', *RUNTIME], cwd=ROOT).split(b'\0')
    lines = b''.join(digest(ROOT/p.decode()).encode()+b'  '+p+b'\n' for p in sorted(files) if p)
    return {'head':git('rev-parse','HEAD'), 'runtime_aggregate':hashlib.sha256(lines).hexdigest(),
            'checker_sha256':digest(ROOT/'apps/ai/app/retrieval/interpretation.py')}
if sys.argv[1] == 'prepare':
    assert git('rev-parse','HEAD') == git('rev-parse','origin/main')
    assert not git('diff','HEAD','--',*RUNTIME)
    base=ROOT/'docs/evaluation/r28-s04/r06-governed-evidence/R28-R07-BLOCK1-CLOSING-TAIL-20261112-01/cases'
    items=[]
    for prefix in ['01-', '02-']:
        p=next(base.glob(prefix+'*.json')); raw=json.loads(p.read_text())
        plan=raw['observation']['production_trace']['needs_plan']
        items.append({'probe_id':raw['case_id'], 'user_turns':[{'turn_id':'user-1','text':raw['utterance']}],
                      'plan':{'schema_version':'evidence-needs-plan-v3',**plan},
                      'source':str(p.relative_to(ROOT)), 'source_sha256':digest(p)})
    write('inputs.json', {'items':items, 'adaptation':'Only schema_version envelope restored; every recorded need and original user turn unchanged.'})
    write('freeze-before.json', freeze())
    write('authorization.json', {'authorized_by':'David, direct user message 2026-11-12',
        'scope':'two existing accepted plans; current check only, once each; no planner/retrieval/generation',
        'max_usd':0.50, 'max_calls':2, 'max_completion_tokens_per_call':4096,
        'input_sha256':digest(OUT/'inputs.json'), 'runtime':freeze(),
        'criterion':'Each must be scope_added or meaning_changed with citation_verification verified.',
        'pricing_basis':'Retained gpt-5-mini snapshot: USD 0.25/M input, 2/M output; estimate, not billed invoice.'})
    print('Frozen; no provider calls')
elif sys.argv[1] == 'execute':
    auth=json.loads((OUT/'authorization.json').read_text())
    assert freeze() == auth['runtime']
    assert digest(OUT/'inputs.json') == auth['input_sha256']
    items=json.loads((OUT/'inputs.json').read_text())['items']
    worker=(ROOT/'scripts/evaluation/block1_interpretation_probe.py').read_text()
    # Production model/prompt/token ceiling unchanged. Guards precede any request.
    prefix="""from app.settings import get_settings
import hashlib
from pathlib import Path
assert get_settings().retrieval_planner_model == 'gpt-5-mini'
assert hashlib.sha256(Path('/app/app/retrieval/interpretation.py').read_bytes()).hexdigest() == CHECKER_HASH
""".replace('CHECKER_HASH',repr(auth['runtime']['checker_sha256']))
    # Entire UTF-8 request/schema/prompt comfortably below 100k tokens per call;
    # reserve 100k input + full 4096 completion = $0.033192 each, $0.066384 total.
    assert sum(len(json.dumps(i).encode()) for i in items) < 30000
    assert len((ROOT/'apps/ai/app/retrieval/interpretation.py').read_bytes()) < 20000
    write('consumed.json', {'at':datetime.now(timezone.utc).isoformat(), 'reserved_usd':0.066384,
                            'basis':'100k input tokens plus 4096 output tokens per call at retained rates'})
    with (OUT/'worker-used.py').open('x') as f: f.write(prefix+worker)
    for idx,item in enumerate(items,1):
        write(f'dispatch-{idx}.json', {'probe_id':item['probe_id'],'started_at':datetime.now(timezone.utc).isoformat()})
        payload={k:item[k] for k in ['probe_id','user_turns','plan']}
        result=subprocess.run([*COMPOSE,'exec','-T','ai','/app/.venv/bin/python','-c',prefix+worker],
            cwd=ROOT,input=json.dumps(payload),text=True,capture_output=True,timeout=240)
        if result.returncode:
            write(f'result-{idx}.json',{'execution_failed':True,'returncode':result.returncode})
            raise SystemExit('Execution failed; no retry')
        record=json.loads(result.stdout);write(f'result-{idx}.json',record)
        print(json.dumps(record),flush=True)
    write('freeze-after.json',freeze())
    assert freeze()==auth['runtime']
