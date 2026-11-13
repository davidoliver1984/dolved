"""Fixed checker-only experiment; same exclusive-consumption/hash-bound probe pattern.

No planner, retrieval, generation, auto-retry, sample reduction or expected verdict
in a provider request. Prepare is provider-free. Execute requires a pushed hold SHA.
"""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
from datetime import datetime, timezone

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[3]
sys.path.insert(0, str(ROOT / 'scripts/evaluation'))
import run_r28_r06_governed as g

BRANCH = 'codex/block1-extractive-hold-20261112'
FILES = ['probes.json', 'expectations.json', 'php-plan-validation.json', 'suite-comparison.json']


def write(name, value):
    g.write_exclusive(OUT / name, value)


def freeze():
    return {'head': g.git('rev-parse', 'HEAD'), 'runtime_source_aggregate': g.runtime_aggregate(),
            'checker_sha256': g.sha256(ROOT / 'apps/ai/app/retrieval/interpretation.py'),
            'runner_sha256': g.sha256(Path(__file__)),
            'worker_sha256': g.sha256(ROOT / 'scripts/evaluation/block1_interpretation_probe.py'),
            'inputs': {f: g.sha256(OUT / f) for f in FILES}}


def check_pin(expected):
    if g.git('branch', '--show-current') != BRANCH or freeze() != expected:
        raise RuntimeError('Pinned runtime/input identity changed')
    if g.git('diff', 'HEAD', '--', *g.RUNTIME_PATHS):
        raise RuntimeError('Runtime is not committed')
    remote = g.git('ls-remote', 'origin', 'refs/heads/' + BRANCH).split()[0]
    if remote != expected['head']:
        raise RuntimeError('Remote hold SHA mismatch')


def prepare():
    state = freeze()
    check_pin(state)
    items = g.json_file(OUT / 'probes.json')['items']
    assert len(items) == 25
    assert len({i['probe_id'] for i in items}) == 25
    assert sum(i['probe_id'].endswith('-correct') for i in items) == 12
    suites = g.json_file(OUT / 'suite-comparison.json')['suites']
    assert all(not x['new'] for x in suites.values())
    code = '''import json,sys
from app.retrieval.interpretation import INSTRUCTIONS,TemporalInterpretationDecision
from app.settings import get_settings
assert get_settings().retrieval_planner_model == 'gpt-5-mini'
items=json.load(sys.stdin)['items']
schema=TemporalInterpretationDecision.model_json_schema()
sizes=[len(json.dumps({'messages':[{'role':'system','content':INSTRUCTIONS}, {'role':'user','content':json.dumps({'user_turns':i['user_turns'],'plan':i['plan']},ensure_ascii=False)}], 'response_format':schema},ensure_ascii=False).encode())+1024 for i in items]
print(json.dumps({'input_token_bounds':sizes,'model':'gpt-5-mini'}))
'''
    result = subprocess.run([*g.COMPOSE, 'exec', '-T', 'ai', '/app/.venv/bin/python', '-c', code],
                            input=json.dumps({'items': items}), text=True, capture_output=True, check=True)
    bounds = json.loads(result.stdout)
    input_bound = sum(bounds['input_token_bounds']) * 5
    output_bound = 125 * 4096
    reservation = input_bound * .25 / 1e6 + output_bound * 2 / 1e6
    write('authorization.json', {'authorization_id':'R28-R07-AUTH-TEMPORAL-PROBES-20261113-01',
        'approved_by':'David', 'approved_on':'2026-11-13', 'scope':'125 checker-only calls, no planner calls',
        'runtime':state, 'max_calls':125, 'attempts_per_plan':5, 'max_completion_tokens':4096,
        'reservation_usd':reservation, 'input_token_bound':input_bound, 'output_token_bound':output_bound,
        'input_bounds_per_plan':bounds['input_token_bounds'],
        'pricing_basis':'Retained gpt-5-mini USD0.25/M input, USD2/M output, no cache discount; not billing invoice. UTF8 bytes plus 1024 framing tokens per call is a conservative input bound. David removed the cap; production output ceiling unchanged.'})
    write('freeze-before.json', state)
    print(json.dumps({'reserved_usd':reservation, 'calls':125,'input_token_bound':input_bound,'output_token_bound':output_bound}))


def execute():
    auth = g.json_file(OUT / 'authorization.json')
    check_pin(auth['runtime'])
    write('consumed.json', {'authorization_id':auth['authorization_id'], 'at':datetime.now(timezone.utc).isoformat(),
                             'reservation_usd':auth['reservation_usd']})
    worker = (ROOT / 'scripts/evaluation/block1_interpretation_probe.py').read_text()
    prefix = "from app.settings import get_settings\nimport hashlib\nfrom pathlib import Path\nassert get_settings().retrieval_planner_model == 'gpt-5-mini'\nassert hashlib.sha256(Path('/app/app/retrieval/interpretation.py').read_bytes()).hexdigest() == " + repr(auth['runtime']['checker_sha256']) + '\n'
    (OUT / 'results').mkdir()
    try:
        for item in g.json_file(OUT / 'probes.json')['items']:
            for attempt in range(1,6):
                if freeze() != auth['runtime']:
                    raise RuntimeError('Runtime or frozen input drift')
                identity = f"{item['probe_id']}-{attempt}"
                with (OUT / 'dispatch.jsonl').open('a') as ledger:
                    ledger.write(json.dumps({'id':identity,'started_at':datetime.now(timezone.utc).isoformat()})+'\n')
                    ledger.flush()
                    os.fsync(ledger.fileno())
                result = subprocess.run([*g.COMPOSE,'exec','-T','ai','/app/.venv/bin/python','-c',prefix+worker],
                    input=json.dumps(item), text=True, capture_output=True, timeout=240)
                if result.returncode:
                    write('results/'+identity+'.json', {'execution_failed':True,'returncode':result.returncode})
                    raise RuntimeError('Probe execution failure; no retry')
                record = json.loads(result.stdout)
                write('results/'+identity+'.json', record)
                print(identity, record['receipt']['code'], record['receipt']['citation_verification'], flush=True)
        write('freeze-after.json', freeze())
        assert freeze() == auth['runtime']
    finally:
        g.write_inventory(OUT)


if __name__ == '__main__':
    {'prepare':prepare, 'execute':execute}[sys.argv[1]]()
