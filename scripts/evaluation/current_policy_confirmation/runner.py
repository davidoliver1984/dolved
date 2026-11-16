"""One-use current-policy product runner. No live authority is supplied by this file.

Retains the existing exclusive-start + per-item maximum-liability pattern. Browser
submission uses the product UI; export reads the resulting production run only.
Unknown/missing usage retains the entire item reservation, never a zero estimate.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import time
from decimal import Decimal


def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def durable(path, value, exclusive=False):
    with Path(path).open('x' if exclusive else 'w') as stream:
        json.dump(value, stream, indent=2)
        stream.flush()
        os.fsync(stream.fileno())


def verify(binding, manifest):
    if digest(binding['manifest']) != binding['manifest_sha256']:
        raise ValueError('manifest drift')
    if set(manifest) != {'schema_version', 'package_id', 'note', 'evaluated_at_assumption', 'items'}:
        raise ValueError('released manifest shape')
    if any(set(item) != {'id', 'utterance'} for item in manifest['items']):
        raise ValueError('item shape')
    if len({item['id'] for item in manifest['items']}) != len(manifest['items']):
        raise ValueError('duplicate item')
    if not binding['provider_free'] and len(manifest['items']) != 68:
        raise ValueError('expected 68 released items')
    if not binding['files'] or not binding['mounted_verify_command']:
        raise ValueError('unbound executable/runtime verification')
    if subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=binding['worktree'], text=True).strip() != binding['commit']:
        raise ValueError('source commit drift')
    for path, expected in binding['files'].items():
        if digest(Path(binding['worktree']) / path) != expected:
            raise ValueError('source or harness drift: '+path)
    # Exact executable command and mounted hash list are release-bound files above.
    subprocess.run(binding['mounted_verify_command'], check=True, stdout=subprocess.DEVNULL)
    if time.time() >= binding['deadline_epoch']:
        raise ValueError('deadline exhausted')


def reserve(ledger, item, maximum, ceiling):
    committed = sum(Decimal(row['liability_usd']) for row in ledger)
    if maximum < 0 or committed + maximum > ceiling:
        raise ValueError('budget refusal before dispatch')
    if any(row['id'] == item for row in ledger):
        raise ValueError('duplicate dispatch')
    ledger.append({'id':item,'started_at':time.time(),'liability_usd':str(maximum),'state':'reserved_before_dispatch'})


def safe_item_outcome(saved):
    if saved['status'] in {'completed', 'retrieval_no_answer', 'clarification_required'}:
        return True
    planner = (saved.get('usage') or {}).get('planner_failure', {})
    return (saved['status'] == 'failed'
            and planner.get('category') == 'invalid_grounded_needs_plan'
            and planner.get('provider_status') == 200
            and planner.get('systemic') is False)


def execute(binding):
    manifest = json.loads(Path(binding['manifest']).read_text())
    verify(binding, manifest)
    total = sum(Decimal(amount) for item in manifest['items']
                for amount in binding['items'][item['id']]['maximum_liability_by_stage'].values())
    if total > Decimal(binding['ceiling_usd']):
        raise ValueError('total reservation exceeds ceiling before start')
    if not binding['provider_free']:
        # No operational authorisation is created by Stop 3 or inferred from $300.
        authority = json.loads(Path(binding['authorization']).read_text())
        if digest(binding['authorization']) != binding['authorization_sha256'] or authority['binding_sha256'] != binding['binding_sha256'] or authority['approved'] is not True:
            raise ValueError('release authorisation missing or mismatched')
        consumed = Path(binding['authorization'] + '.consumed')
        if consumed.exists():
            raise ValueError('authorisation already consumed')
        if total <= 0 or not binding.get('pricing_snapshot_sha256') or not binding.get('reviewer_context_sha256'):
            raise ValueError('release pricing/context missing')
    destination = Path(binding['destination'])
    destination.mkdir(exist_ok=False)
    if not binding['provider_free']:
        consumed.mkdir(exist_ok=False)
    durable(destination/'started.json', {'provider_free':binding['provider_free'],'time':time.time(), 'manifest_sha256':binding['manifest_sha256']}, True)
    durable(destination/'manifest.json', manifest, True)
    durable(destination/'reservation.json', {'maximum_usd':str(total),'ceiling_usd':binding['ceiling_usd'],
                                           'evaluated_at_assumption':manifest['evaluated_at_assumption']}, True)
    ledger=[]
    for item in manifest['items']:
        verify(binding, manifest)
        bound = binding['items'][item['id']]
        context = {'actor':bound['actor'],'workspace':bound['browser_context']['workspace']}
        preflight = subprocess.run(binding['preflight_command'], input=json.dumps(context), text=True, capture_output=True, check=True)
        if json.loads(preflight.stdout) != bound['expected_preflight']:
            raise ValueError('actor/workspace/generation/profile drift')
        stages = bound['maximum_liability_by_stage']
        if set(stages) != {'planning_including_intrinsic_calls', 'retrieval_including_retries', 'generation'}:
            raise ValueError('incomplete attempt reservation')
        reserve(ledger,item['id'],sum(Decimal(v) for v in stages.values()),Decimal(binding['ceiling_usd']))
        durable(destination/'ledger.json',ledger)
        input_path=destination/(item['id']+'-browser-input.json')
        durable(input_path, {**bound['browser_context'],'id':item['id'],'utterance':item['utterance'],
                             'run_timeout_seconds':bound['expected_preflight']['run_timeout_seconds']},True)
        output=destination/item['id']
        try:
            subprocess.run(['node', str(Path(__file__).with_name('browser-check.mjs')),str(output),str(input_path)], check=True,
                           env={**os.environ, 'CONFIRMATION_DEADLINE_EPOCH':str(binding['deadline_epoch'])})
            records=json.loads((output/'records.json').read_text())
            public=records[0]['backend']['data']['runs'][-1]
            capture={'actor':bound['actor'],'workspace':bound['browser_context']['workspace'],'run':public['id']}
            response=subprocess.run(binding['capture_command'],input=json.dumps(capture),text=True,capture_output=True,check=True)
            saved=json.loads(response.stdout)
            durable(output/'persisted.json',saved,True)
            if saved['text'] != records[0]['completed']['text'] or saved['run_id'] != public['id']:
                raise ValueError('persistence/browser integrity mismatch')
            if saved['needs_profile'] != bound['expected_preflight']['profile']:
                raise ValueError('executed profile drift')
            if not safe_item_outcome(saved):
                raise ValueError('typed failure requires halt or cannot be safely classified')
            # Keep full maximum if any stage lacks complete, independently attributable usage.
            # Do not add nested receipts to their parent aggregates or release unknown liability.
            ledger[-1].update(state='recorded', accounting='full maximum retained; receipts saved without double-counting')
            durable(destination/'ledger.json',ledger)
        except Exception as error:
            ledger[-1].update(state='halt_uncertain_or_failed_capture',failure_type=type(error).__name__)
            durable(destination/'ledger.json',ledger)
            raise
    verify(binding,manifest)
    durable(destination/'completed.json',{'items':len(ledger),'provider_free':binding['provider_free']},True)


if __name__ == '__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('binding')
    args=parser.parse_args()
    data=json.loads(Path(args.binding).read_text())
    data['binding_sha256']=digest(args.binding)
    execute(data)
