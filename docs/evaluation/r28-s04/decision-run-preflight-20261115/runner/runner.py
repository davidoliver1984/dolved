"""Production callbacks for the unchanged checkpoint; no authorisation builder.

run_once.sh owns Docker commands, as in the C2 runner. This process runs in the
existing AI image. A fixed-operation pipe avoids a host Python or Docker socket
inside the container. Tests substitute the operations, never production providers.
"""
import hashlib
import json
import sys
from datetime import datetime, timezone
from pathlib import Path
from accounting import Accounting, atomic

HERE = Path(__file__).resolve().parent
PREFLIGHT = HERE.parent
sys.path.insert(0, str(PREFLIGHT/'completion'))
sys.path.insert(0, str(PREFLIGHT/'harness'))
from released_input import parse_release, execute_released

WORKSPACE = '5323cd6b-8b7a-40f9-98b0-9413c75f643e'
GENERATION = 'b24a79c2-875e-4f7c-8fa9-3681db92ae73'
ACTOR = 'ae454ce6-4d91-4c7e-9a40-b4750b696a6b'
REVIEW = '75f564ef5b8076bc9b6f7cfe67b647176a2f532704812d5cd9933419ea37fbce'
BASELINE = 'b98569487046d1649003ccffa429c90678482c1c'


def digest(path): return hashlib.sha256(Path(path).read_bytes()).hexdigest()
def now(): return datetime.now(timezone.utc).isoformat()
def manifest(root, path):
    for line in Path(path).read_text().splitlines():
        expected, name = line.split('  ', 1)
        if digest(root/name) != expected: raise ValueError('dependency_drift:' + name)


class Runner:
    def __init__(self, root, destination, authority, bridge):
        self.root, self.destination, self.authority, self.bridge = Path(root), Path(destination), authority, bridge
        self.release = self.root/'docs/evaluation/r28-s04/decision-run-20261115/released-input/questions-only.json'
        self.items = parse_release(self.release.read_bytes())
        self.position = 0; self.checks = 0; self.pending = None
        self.account = Accounting(self.destination/'accounting.json', json.loads((PREFLIGHT/'mechanical/reservation.json').read_text()))

    def verify(self):
        a = self.authority
        mechanical = json.loads((PREFLIGHT/'mechanical/identity.json').read_text())
        for key, value in {'execution_baseline': BASELINE, 'workspace': WORKSPACE, 'actor': ACTOR,
                           'generation': GENERATION, 'reviewer_confirmation_sha256': REVIEW,
                           'reservation_usd': '136.737303', 'hard_stop_usd': '273.474606'}.items():
            if a.get(key) != value: raise ValueError('authority_binding:' + key)
        for path, expected in [(PREFLIGHT/'runtime-source.sha256', mechanical['runtime_sha256']),
                               (PREFLIGHT/'mechanical/harness.sha256', mechanical['harness_sha256']),
                               (PREFLIGHT/'mechanical/dependencies.sha256', mechanical['dependencies_sha256']),
                               (HERE/'executable.sha256', a.get('harness_sha256')),
                               (self.release, a.get('released_input_sha256')),
                               (self.release.parent.parent/'reviewer-predispatch-confirmation.md', REVIEW)]:
            if digest(path) != expected: raise ValueError('binding_drift:' + path.name)
        for path in [PREFLIGHT/'runtime-source.sha256', PREFLIGHT/'mechanical/harness.sha256',
                     PREFLIGHT/'mechanical/dependencies.sha256', HERE/'executable.sha256']:
            manifest(self.root, path)
        if a.get('dependencies_sha256') != mechanical['dependencies_sha256']: raise ValueError('dependencies_identity')
        self.checks += 1
        self.bridge('VERIFY', str(self.checks))
        checks = self.destination/'integrity'/str(self.checks)
        context = json.loads((checks/'context.json').read_text())
        for k, v in {'actor': ACTOR, 'workspace': WORKSPACE, 'generation': GENERATION, 'site_assignment': None,
                     'membership': '6bf6a4a2-d235-4dc1-8be8-dda675cfdfca', 'role': 'owner'}.items():
            if context.get(k) != v: raise ValueError('context_drift:' + k)
        preflight = json.loads((checks/'preflight.json').read_text())
        if preflight['corpus']['id'] != GENERATION: raise ValueError('generation_drift')
        host = json.loads((checks/'host.json').read_text())
        if host['commit'] != a['commit'] or host['branch'] != 'codex/block1-extractive-hold-20261112': raise ValueError('git_drift')
        if host['override_sha256'] != json.loads((PREFLIGHT/'mechanical/recovery.json').read_text())['override_sha256']: raise ValueError('override_drift')
        running = json.loads((checks/'running.json').read_text())
        if set(running) != {'api','ai'} or not all(v['all_environment_values_verified'] and v['healthy'] for v in running.values()): raise ValueError('deployment_drift')
        return {'commit': a['commit'], 'harness_sha256': a['harness_sha256'], 'dependencies_sha256': a['dependencies_sha256'], 'runtime': mechanical['runtime_sha256']}

    def budget_check(self):
        # checkpoint calls once before consumption, then once before each item.
        # The same item is reserved only once; a new invocation refuses old state.
        item = self.items[self.position]['id']
        if self.pending == item: return
        self.account.reserve(item); self.pending = item

    def dispatch(self, payload):
        item = self.items[self.position]
        if payload != {'id': item['id'], 'utterance': item['utterance']} or self.pending != item['id']: raise ValueError('dispatch_order_or_reservation')
        folder = self.destination/'items'/item['id']; folder.mkdir(parents=True)
        atomic(folder/'input.json', payload)
        atomic(folder/'timing.json', {'dispatch_start_utc': now(), 'timezone': 'Europe/London'})
        try:
            self.bridge('DISPATCH', item['id'])
            record = json.loads((folder/'raw.json').read_text())
            # Settle before validating capture/persistence so a failed post-check
            # cannot lose the spent item's liability.
            self.account.settle(item['id'], record)
            atomic(folder/'persistence-input.json', {'items':[{'item':item['id'],'run_id':record['run_id']}]})
            self.bridge('PERSIST', item['id'])
            self.check_record(folder, payload, record)
            return record
        finally:
            atomic(folder/'finished.json', {'finished_utc':now(), 'timezone':'Europe/London'})
            self.position += 1; self.pending = None

    def check_record(self, folder, payload, record):
        if record.get('input') != payload: raise ValueError('record_input_mismatch')
        previous = ''
        for n, line in enumerate((folder/'ledger.ndjson').read_text().splitlines(), 1):
            event = json.loads(line); unhashed = line.rsplit(',"hash":',1)[0] + '}'
            if event['sequence'] != n or event['previous_hash'] != previous or hashlib.sha256(unhashed.encode()).hexdigest() != event['hash']: raise ValueError('ledger_integrity')
            previous = event['hash']
        if not previous: raise ValueError('missing_capture_ledger')
        persisted = json.loads((folder/'persistence.json').read_text())['items']
        if len(persisted) != 1: raise ValueError('persistence_count')
        p = persisted[0]
        if any(p[k] != v for k,v in {'workspace_id':WORKSPACE,'actor_id':ACTOR,'question':payload['utterance'], 'user_turns':1,'generation_runs':1,'run_id':record['run_id']}.items()): raise ValueError('persistence_identity')
        if p['assistant_display_text'] != record['displayed_text'] or p['reloaded_rendered_text'] != record['reloaded_rendered_text']: raise ValueError('persistence_text_changed')
        if record['temporal_context'] != record['reloaded_temporal_context'] and not (not record['temporal_context'] and record['reloaded_temporal_context'] is None): raise ValueError('temporal_reload_mismatch')
        captured = []
        for call in record.get('service_calls', []):
            if not call['path'].endswith(('/plan-needs','/answer-needs')): continue
            request = call.get('request_id')
            from uuid import UUID
            UUID(request)
            paths = sorted((self.destination/'capture').glob(request+'-*.json'))
            # A transport outage may have no serving-side receipt: the frozen
            # typed stop gate decides that outcome, never reinterpret it here.
            planner_receipt = call['path'].endswith('/plan-needs') and (
                call.get('http_status') == 200 or (call.get('planner_detail') or {}).get('provider_status') == 200)
            generation_rejection = call['path'].endswith('/answer-needs') and call.get('error_category') == 'contract_validation_failure'
            # Generation sidecars record rejections only. A successful first
            # generation attempt correctly has no sidecar; its receipt is in trace.
            if not paths and call.get('status') == 'received' and (planner_receipt or generation_rejection): raise ValueError('missing_attempt_capture')
            for path in paths:
                sidecar = json.loads(path.read_text())
                if sidecar['workspace_id'] != WORKSPACE or sidecar['request_id'] != request: raise ValueError('capture_identity')
                captured.append({'file':path.name,'sha256':digest(path)})
        atomic(folder/'capture-check.json', {'sidecars':captured,'raw_source_review_json':'unavailable','backend_representations_equal':record['displayed_text']==record['reloaded_rendered_text'],'grading':False})

    def run(self):
        if any((self.destination/name).exists() for name in ('authorization-consumed.json','accounting.json')) or any(self.destination.glob('*.started.json')) or any(self.destination.glob('*.record.json')) or (self.destination/'items').exists():
            raise ValueError('prior_run_state_refuse')
        try:
            result = execute_released(self.release.read_bytes(), self.authority, self.destination, self.verify, self.dispatch, self.budget_check)
            # Record final verification even after a typed item stop; never resume.
            self.verify()
            atomic(self.destination/'result.json', result)
            return result
        except Exception as error:
            # Bounded type only; no unrestricted provider/exception prose.
            atomic(self.destination/'runner-stop.json', {'type':type(error).__name__, 'at':now(), 'completed_callbacks':self.position, 'resume_authorised':False})
            raise


def pipe_bridge(operation, value):
    print(operation + ' ' + value, flush=True)
    if sys.stdin.readline().strip() != 'OK': raise RuntimeError('host_operation_failed')


if __name__ == '__main__':
    # Authority must be separately approved and supplied; this runner never creates it.
    root = Path('/workspace')
    destination = root/'docs/evaluation/r28-s04/decision-run-20261115/backend-run'
    authority = json.loads((destination/'authorization.json').read_text())
    try: Runner(root,destination,authority,pipe_bridge).run()
    except Exception:
        print('HALT 0',flush=True); raise SystemExit(1)
    print('DONE 0',flush=True)
