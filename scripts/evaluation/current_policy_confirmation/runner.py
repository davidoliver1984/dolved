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
    if binding.get('require_native_capture') and not binding.get('native_capture'):
        raise ValueError('required native capture observer not bound')
    if digest(binding['manifest']) != binding['manifest_sha256']:
        raise ValueError('manifest drift')
    if set(manifest) != {'schema_version', 'package_id', 'note', 'evaluated_at_assumption', 'items'}:
        raise ValueError('released manifest shape')
    if any(set(item) != {'id', 'utterance'} for item in manifest['items']):
        raise ValueError('item shape')
    if len({item['id'] for item in manifest['items']}) != len(manifest['items']):
        raise ValueError('duplicate item')
    expected = binding.get('expected_item_count')
    if type(expected) is not int or expected < 1 or len(manifest['items']) != expected:
        raise ValueError('manifest count differs from bound expected_item_count')
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


def require_item_window(binding):
    """Confirmation-only bound; never reset any existing clock."""
    minimum = binding.get('minimum_item_window_seconds', 0)
    if type(minimum) is not int or minimum < 0:
        raise ValueError('invalid minimum item window')
    if binding['deadline_epoch'] - time.time() < minimum:
        raise ValueError('insufficient remaining item window before dispatch')


def safe_item_outcome(saved):
    generation = ((saved.get('answer') or {}).get('temporal_context') or {}).get('current_policy', {}).get('generation') or {}
    if generation.get('status') == 'failed' and generation.get('reason') not in {'invalid_output','incomplete_output'}:
        # Includes schema rejection collapsed by the existing adapter into
        # provider_unavailable. Do not spend another item to diagnose it.
        return False
    if saved['status'] in {'completed', 'retrieval_no_answer', 'clarification_required'}:
        return True
    planner = (saved.get('usage') or {}).get('planner_failure', {})
    return (saved['status'] == 'failed'
            and planner.get('category') == 'invalid_grounded_needs_plan'
            and planner.get('provider_status') == 200
            and planner.get('systemic') is False)


def prepare_destination(binding):
    """Retain reviewed Phase A files; never erase operational state."""
    destination = Path(binding['destination'])
    if not binding.get('preparation_files'):
        destination.mkdir(exist_ok=False)
        return destination
    allowed = set(binding['preparation_files']) | {'binding.json'}
    empty_native_directories = {'capture'}
    if binding.get('native_capture'):
        for key, name in [('journal','observer-journal'),('health','observer-health'),('capture','capture')]:
            if Path(binding['native_capture'][key]) != destination/name:
                raise ValueError('native capture evidence path must be explicitly bound inside destination')
            empty_native_directories.add(name)
    if binding.get('preparation_checksums'):
        manifest = destination/binding['preparation_checksums']
        entries=dict((n,h) for h,n in (line.split('  ',1) for line in manifest.read_text().splitlines()))
        if set(entries)!=allowed or any(digest(destination/n)!=h for n,h in entries.items()):
            raise ValueError('preparation checksum manifest drift')
        allowed.add(binding['preparation_checksums'])
    for name, expected in binding['preparation_files'].items():
        if digest(destination/name) != expected:
            raise ValueError('preparation evidence drift: '+name)
    if digest(destination/'binding.json') != binding['binding_sha256']:
        raise ValueError('preparation binding drift')
    for path in destination.rglob('*'):
        name = str(path.relative_to(destination))
        if path.is_symlink() or (path.is_file() and name not in allowed):
            raise ValueError('prior or unbound operational state: '+name)
        if path.is_dir() and name not in empty_native_directories and not any(n.startswith(name+'/') for n in allowed):
            raise ValueError('prior or unbound operational directory: '+name)
    return destination


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
        # A later authority contains this preparation binding's hash. Its own
        # hash is supplied separately at release, avoiding a circular hash.
        authority_hash = binding.get('authorization_sha256') or os.environ.get('CURRENT_POLICY_AUTHORIZATION_SHA256')
        if not authority_hash or digest(binding['authorization']) != authority_hash or authority['binding_sha256'] != binding['binding_sha256'] or authority['approved'] is not True:
            raise ValueError('release authorisation missing or mismatched')
        consumed = Path(binding['authorization'] + '.consumed')
        if consumed.exists():
            raise ValueError('authorisation already consumed')
        if total <= 0 or not binding.get('pricing_snapshot_sha256') or not binding.get('reviewer_context_sha256'):
            raise ValueError('release pricing/context missing')
    destination = prepare_destination(binding)
    if not binding['provider_free']:
        consumed.mkdir(exist_ok=False)
    durable(destination/'started.json', {'provider_free':binding['provider_free'],'time':time.time(), 'manifest_sha256':binding['manifest_sha256']}, True)
    durable(destination/'manifest.json', manifest, True)
    durable(destination/'reservation.json', {'maximum_usd':str(total),'ceiling_usd':binding['ceiling_usd'],
                                           'evaluated_at_assumption':manifest['evaluated_at_assumption']}, True)
    if binding.get('worker_start_command'):
        # All three commands are frozen dependencies. Guard rechecks an empty
        # conversation queue and no consumers immediately before starting.
        subprocess.run(binding['worker_guard_command'],check=True,stdout=subprocess.DEVNULL)
        started=time.time()
        binding={**binding,'deadline_epoch':min(binding['deadline_epoch'],started+binding['worker_window_seconds'])}
        durable(destination/'worker-timing.json',{'worker_start_epoch':started,'deadline_epoch':binding['deadline_epoch']},True)
        try:
            subprocess.run(binding['worker_start_command'],check=True)
            execute_items(binding,manifest,destination)
        finally:
            subprocess.run(binding['worker_stop_command'],check=True)
            if binding.get('native_capture'):
                from native_capture import verify_shutdown
                try:
                    verify_shutdown(binding['native_capture'], destination, durable)
                except Exception as error:
                    durable(destination/'observer-shutdown-halt.json', {'code':getattr(error,'code','observer_shutdown_failure')}, True)
                    raise
    else:
        execute_items(binding,manifest,destination)


def execute_items(binding,manifest,destination):
    ledger=[]
    conversation_ids=set()
    native = None
    if binding.get('native_capture'):
        from native_capture import Reconciler
        native = Reconciler(binding['native_capture'], destination, durable)
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
        require_item_window(binding)
        if native:
            try:
                native.before(item['id'])
            except Exception as error:
                durable(destination/(item['id']+'-native-halt.json'), {'code':getattr(error,'code','observer_read_failure'),'before_dispatch':True}, True)
                raise
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
            conversation=records[0]['backend']['data']['id']
            if conversation in conversation_ids:
                raise ValueError('conversation reused across items')
            conversation_ids.add(conversation)
            if binding.get('submission_window_seconds') and len(ledger)==1:
                binding={**binding,'deadline_epoch':min(binding['deadline_epoch'],records[0]['timing']['submitted_at_ms']/1000+binding['submission_window_seconds'])}
                durable(destination/'submission-deadline.json',{'deadline_epoch':binding['deadline_epoch'],'first_submission_ms':records[0]['timing']['submitted_at_ms']},True)
            if time.time()>=binding['deadline_epoch']:
                raise ValueError('execution deadline exhausted')
            public=records[0]['backend']['data']['runs'][-1]
            capture={'actor':bound['actor'],'workspace':bound['browser_context']['workspace'],'run':public['id']}
            response=subprocess.run(binding['capture_command'],input=json.dumps(capture),text=True,capture_output=True,check=True)
            saved=json.loads(response.stdout)
            durable(output/'persisted.json',saved,True)
            if saved['text'] != records[0]['completed']['text'] or saved['run_id'] != public['id']:
                raise ValueError('persistence/browser integrity mismatch')
            if saved['needs_profile'] != bound['expected_preflight']['profile']:
                raise ValueError('executed profile drift')
            if native:
                observed_deadline = min(binding['deadline_epoch'], records[0]['timing']['submitted_at_ms']/1000 + bound['expected_preflight']['run_timeout_seconds'] + 15)
                native.after(item['id'], public['id'], saved, observed_deadline)
            if not safe_item_outcome(saved):
                raise ValueError('typed failure requires halt or cannot be safely classified')
            # Keep full maximum if any stage lacks complete, independently attributable usage.
            # Do not add nested receipts to their parent aggregates or release unknown liability.
            ledger[-1].update(state='recorded', accounting='full maximum retained; receipts saved without double-counting')
            durable(destination/'ledger.json',ledger)
        except Exception as error:
            ledger[-1].update(state='halt_uncertain_or_failed_capture',failure_type=type(error).__name__)
            if native:
                durable(destination/(item['id']+'-native-halt.json'), {'code':getattr(error,'code','observation_or_dispatch_failure'),'before_dispatch':False}, True)
            durable(destination/'ledger.json',ledger)
            raise
    verify(binding,manifest)
    if native:
        native.finish()
    durable(destination/'completed.json',{'items':len(ledger),'provider_free':binding['provider_free']},True)


if __name__ == '__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('binding')
    args=parser.parse_args()
    data=json.loads(Path(args.binding).read_text())
    data['binding_sha256']=digest(args.binding)
    execute(data)
