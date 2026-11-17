"""Exact observer-ID reconciliation. No model calls, repairs or time correlation."""
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import time

UUID = re.compile(r'^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$')
STAGES = {'planner', 'current_policy_generation'}


class CaptureError(ValueError):
    def __init__(self, code):
        self.code = code
        super().__init__(code)


class Pending(CaptureError):
    pass


def fail(code):
    raise CaptureError(code)


def inventory(root, limit=140000):
    """No symlinks, subdirectories or pathname-following reads."""
    root = Path(root)
    if root.is_symlink() or str(root.resolve()) != str(root):
        fail('capture_root_not_real')
    fd = os.open(root, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    result = {}
    try:
        names = sorted(os.listdir(fd))
        if len(names) > 10000:
            fail('inventory_count_bound')
        for name in names:
            st = os.stat(name, dir_fd=fd, follow_symlinks=False)
            if not stat.S_ISREG(st.st_mode) or st.st_size > limit:
                fail('unexpected_file_type_or_size')
            item = os.open(name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=fd)
            try:
                before = os.fstat(item)
                with os.fdopen(item, 'rb', closefd=False) as stream:
                    raw = stream.read(limit + 1)
                after = os.fstat(item)
                if (before.st_ino, before.st_size, before.st_mtime_ns) != (after.st_ino, after.st_size, after.st_mtime_ns):
                    raise Pending('write_in_progress')
                if len(raw) > limit:
                    fail('file_size_bound')
                result[name] = {'size': len(raw), 'sha256': hashlib.sha256(raw).hexdigest(), 'raw': raw}
            finally:
                os.close(item)
    finally:
        os.close(fd)
    return result


def hashes(rows):
    return {n: {k: v[k] for k in ('size', 'sha256')} for n, v in rows.items()}


def unchanged(previous, current):
    if any(n not in current or current[n] != v for n, v in previous.items()):
        fail('prior_file_changed_or_missing')


def parsed(row):
    def unique(pairs):
        d = {}
        for k, v in pairs:
            if k in d:
                fail('duplicate_json_key')
            d[k] = v
        return d
    try:
        v = json.loads(row['raw'], object_pairs_hook=unique, parse_constant=lambda _: fail('invalid_json_number'))
    except CaptureError:
        raise
    except (ValueError, UnicodeError):
        raise Pending('incomplete_or_malformed_json') from None
    if not isinstance(v, dict):
        fail('record_not_object')
    return v


def journal(spec):
    if inventory(spec['health'], 1024):
        fail('observer_health_failure')
    rows = inventory(spec['journal'], 16384)
    previous = ''
    active = None
    requests = {}
    jobs = []
    stopped = False
    allowed_common = {'schema', 'execution_id', 'worker_id', 'workspace_id', 'sequence', 'previous_sha256', 'kind', 'job_id', 'run_id'}
    extra = {'worker_start': set(), 'worker_stop': set(), 'job_start': set(),
             'job_end': {'job_state', 'run_status'},
             'request_start': {'request_id', 'endpoint', 'stage', 'purpose'},
             'request_end': {'request_id', 'endpoint', 'stage', 'purpose', 'http_status', 'transport', 'attempts', 'generation_status', 'generation_reason'}}
    for sequence, (name, raw) in enumerate(rows.items(), 1):
        r = parsed(raw)
        if name != f'{sequence:06}.json' or type(r.get('sequence')) is not int or r.get('sequence') != sequence or r.get('previous_sha256') != previous:
            fail('journal_sequence_or_integrity')
        if r.get('schema') != 'worker-observer-v1' or any(r.get(k) != spec[k] for k in ('execution_id', 'worker_id', 'workspace_id')):
            fail('journal_identity')
        kind = r.get('kind')
        if kind not in extra or set(r) - allowed_common - extra[kind] or stopped:
            fail('journal_schema')
        if sequence == 1 and kind != 'worker_start':
            fail('missing_worker_start')
        if kind == 'worker_start' and sequence != 1:
            fail('duplicate_worker_start')
        if kind == 'job_start':
            if active is not None or not UUID.fullmatch(str(r.get('run_id', ''))) or not re.fullmatch(r'[a-zA-Z0-9-]{1,100}', str(r.get('job_id', ''))):
                fail('job_overlap_or_identity')
            if any(j['run_id'] == r['run_id'] or j['job_id'] == r['job_id'] for j in jobs):
                fail('duplicate_job_or_run')
            active = {'run_id': r['run_id'], 'job_id': r['job_id'], 'requests': [], 'terminal': None}
            jobs.append(active)
        if kind in {'request_start', 'request_end', 'job_end'}:
            if active is None or any(r.get(k) != active[k] for k in ('run_id', 'job_id')):
                fail('unassociated_job_event')
        if kind in {'request_start', 'request_end'}:
            rid = r.get('request_id')
            expected_path = {'planner': '/api/internal/retrieval/plan-needs', 'current_policy_generation': '/api/internal/retrieval/generation/current-policy-answer'}
            purpose = {'planner': 'retrieval.plan-needs', 'current_policy_generation': 'generation.current-policy-answer'}
            if not UUID.fullmatch(str(rid)) or r.get('endpoint') != expected_path.get(r.get('stage')) or r.get('purpose') != purpose.get(r.get('stage')):
                fail('request_identity_or_endpoint')
            if kind == 'request_start':
                if rid in requests:
                    fail('duplicate_request')
                requests[rid] = {'start': r, 'end': None}
                active['requests'].append(rid)
            else:
                if rid not in requests or requests[rid]['end'] is not None or requests[rid]['start']['stage'] != r['stage']:
                    fail('unmatched_request_terminal')
                requests[rid]['end'] = r
        if kind == 'job_end':
            if any(requests[rid]['end'] is None for rid in active['requests']):
                fail('request_missing_terminal')
            active['terminal'] = r
            active = None
        if kind == 'worker_stop':
            if active is not None:
                fail('worker_stopped_with_active_job')
            stopped = True
        previous = raw['sha256']
    if not rows:
        raise Pending('missing_worker_start')
    for job in jobs:
        counts = {stage: sum(requests[rid]['start']['stage'] == stage for rid in job['requests']) for stage in STAGES}
        if counts['planner'] > 2 or counts['current_policy_generation'] > 1:
            fail('observed_request_bound_exceeded')
    return rows, jobs, requests, active, stopped


def reconcile_captures(spec, captures, requests):
    groups = {}
    for name, raw in captures.items():
        # A partial final file can be considered only under an observed request prefix.
        rid = name[:36]
        if rid not in requests or not re.fullmatch(r'[0-9a-f-]{36}-[0-9a-f-]{36}\.json', name) or not UUID.fullmatch(name[37:-5]):
            fail('unattributable_capture')
        c = parsed(raw)
        if c.get('request_id') != rid or c.get('workspace_id') != spec['workspace_id'] or c.get('capture_version') != 'evaluation-attempt-v1':
            fail('capture_identity_or_version')
        if c.get('stage') != requests[rid]['start']['stage'] or c.get('stage') not in STAGES or not UUID.fullmatch(str(c.get('invocation_id', ''))):
            fail('capture_stage_or_invocation')
        if type(c.get('attempt')) is not int or type(c.get('event_sequence')) is not int:
            fail('capture_number_type')
        if c.get('scope_diagnostics_truncated') or c.get('raw_output_truncated') or c.get('canonical_output_truncated') or 'proposal_exceeded_capture_bound' in c.get('unavailable', []):
            fail('required_capture_truncated')
        groups.setdefault(rid, []).append(c)
    for rid, request in requests.items():
        end = request['end']
        if end is None:
            raise Pending('missing_response_terminal')
        cs = groups.get(rid, [])
        if len({c['invocation_id'] for c in cs}) > 1:
            fail('multiple_invocations_for_request')
        if sorted(c.get('event_sequence', -1) for c in cs) != list(range(1, len(cs) + 1)):
            fail('capture_event_sequence')
        if end['transport'] != 'response':
            fail('transport_failure_capture_expectation_unknown')
        if type(end.get('http_status')) is not int or end['http_status'] not in (200, 503):
            fail('service_or_preboundary_failure')
        if request['start']['stage'] == 'planner':
            expected = end.get('attempts')
            if not isinstance(expected, list) or not 1 <= len(expected) <= 2:
                fail('planner_boundary_or_receipts_unavailable')
            if [a.get('attempt') for a in expected] != list(range(1, len(expected) + 1)) or any(a.get('status') not in {'accepted', 'rejected'} for a in expected):
                fail('invalid_native_attempt_receipts')
            if len(expected) == 2 and expected[0]['status'] != 'rejected':
                fail('invalid_native_correction_receipt')
            if expected[-1]['status'] != ('accepted' if end['http_status'] == 200 else 'rejected'):
                fail('planner_http_receipt_status_mismatch')
            if len(cs) < len(expected):
                raise Pending('missing_planner_capture')
            actual = [{'attempt': c.get('attempt'), 'status': c.get('status')} for c in sorted(cs, key=lambda c: c['event_sequence'])]
            if actual != expected:
                fail('native_attempt_mismatch')
        else:
            if end['http_status'] != 200 or end.get('generation_status') not in ('completed', 'failed', 'capacity'):
                fail('generation_service_failure')
            if end.get('generation_status') == 'failed' and end.get('generation_reason') not in ('invalid_output', 'incomplete_output'):
                fail('generation_systemic_failure')
            if end.get('generation_status') == 'capacity' and cs:
                fail('unexpected_pre_model_diagnostic')
            rejected = end.get('generation_status') == 'failed' and end.get('generation_reason') in {'invalid_output', 'incomplete_output'}
            if any(c.get('attempt') != 1 or c.get('status') not in {'rejected', 'normalised'} for c in cs):
                fail('generation_capture_shape')
            statuses = [c['status'] for c in sorted(cs, key=lambda c: c['event_sequence'])]
            if len(set(statuses)) != len(statuses) or statuses not in ([], ['normalised'], ['rejected'], ['normalised', 'rejected']):
                fail('duplicate_or_ordered_generation_events')
            if rejected and 'rejected' not in statuses:
                raise Pending('missing_generation_rejection')
            if not rejected and 'rejected' in statuses:
                fail('unexpected_generation_rejection')
            for c in cs:
                if c['status'] == 'rejected' and not all(isinstance((c.get('validation') or {}).get(k), str) for k in ('stage', 'field', 'code')):
                    fail('missing_rejection_condition')
                if c['status'] == 'rejected':
                    validation = c['validation']
                    condition = (validation['stage'], validation['code'])
                    allowed = {('completion', 'incomplete_output')} if end.get('generation_reason') == 'incomplete_output' else {
                        ('output_contract', 'structural_validation'), ('output_contract', 'malformed_output'),
                        ('output_contract', 'empty_content'), ('output_contract', 'invalid_disclosure'),
                        ('citation_membership', 'invalid_citation_membership')}
                    if condition not in allowed or not re.fullmatch(r'[a-zA-Z0-9_.-]{1,100}', validation['field']):
                        fail('rejection_condition_receipt_mismatch')
                if c['status'] == 'normalised' and (c.get('reason') != 'exact_duplicate_evidence_ids' or not c.get('citation_normalisation')):
                    fail('missing_normalisation_receipt')
                if c['status'] == 'normalised':
                    changes = c['citation_normalisation']
                    if not isinstance(changes, list) or len(changes) > 30:
                        fail('invalid_normalisation_count')
                    indices = []
                    for change in changes:
                        if not isinstance(change, dict) or set(change) != {'part_index', 'removed_count'} or type(change['part_index']) is not int or type(change['removed_count']) is not int or not 0 <= change['part_index'] < 30 or not 1 <= change['removed_count'] < 15:
                            fail('invalid_normalisation_count')
                        indices.append(change['part_index'])
                    if len(set(indices)) != len(indices):
                        fail('duplicate_normalisation_part')
    return groups


class Reconciler:
    def __init__(self, spec, destination, save):
        self.spec, self.destination, self.save = spec, Path(destination), save
        self.prior_captures, self.prior_journal, self.completed_runs = {}, {}, []

    def before(self, item):
        rows, jobs, _, active, stopped = journal(self.spec)
        current = hashes(inventory(self.spec['capture']))
        unchanged(self.prior_captures, current)
        unchanged(self.prior_journal, hashes(rows))
        if current != self.prior_captures or active is not None or stopped or [j['run_id'] for j in jobs] != self.completed_runs:
            fail('unexpected_between_item_activity')
        self.save(self.destination/(item+'-capture-baseline.json'), {'capture': current, 'journal': hashes(rows)}, True)

    def after(self, item, run, saved, deadline):
        # Five seconds at most, entirely inside the existing submission/overall bound.
        until = min(deadline, time.time() + 5)
        stable = None
        last = 'observation_deadline'
        while time.time() < until:
            try:
                rows, jobs, requests, active, _ = journal(self.spec)
                caps = inventory(self.spec['capture'])
                unchanged(self.prior_captures, hashes(caps)); unchanged(self.prior_journal, hashes(rows))
                if active is not None:
                    raise Pending('missing_job_terminal')
                if [j['run_id'] for j in jobs] != self.completed_runs + [run]:
                    fail('foreign_or_missing_item_job')
                if jobs[-1]['terminal']['run_status'] != saved['status']:
                    fail('job_persistence_status_mismatch')
                owned_requests = [requests[r] for r in jobs[-1]['requests']]
                planner = [r for r in owned_requests if r['start']['stage'] == 'planner']
                if not planner and saved['status'] in {'completed', 'retrieval_no_answer', 'clarification_required'}:
                    fail('missing_observed_planner_request')
                cp = (saved.get('usage') or {}).get('current_policy', {})
                persisted_attempts = cp.get('planner_usage', {}).get('validation_attempts')
                if persisted_attempts is not None:
                    if not planner or [a['status'] for a in persisted_attempts] != [a['status'] for a in (planner[-1]['end'].get('attempts') or [])]:
                        fail('persisted_planner_receipt_mismatch')
                generation = [r for r in owned_requests if r['start']['stage'] == 'current_policy_generation']
                persisted_generation = cp.get('generation')
                if isinstance(persisted_generation, dict) and persisted_generation.get('request_id'):
                    if len(generation) != 1 or generation[0]['start']['request_id'] != persisted_generation['request_id']:
                        fail('persisted_generation_identity_mismatch')
                reconcile_captures(self.spec, caps, requests)
                snapshot = (hashes(caps), hashes(rows))
                if snapshot == stable:
                    owned = {rid for rid in jobs[-1]['requests']}
                    record = {'run_id': run, 'job_id': jobs[-1]['job_id'], 'basis': 'exact_worker_job_request_response_ids',
                              'request_ids': sorted(owned), 'captures': {n: h for n, h in snapshot[0].items() if n[:36] in owned},
                              'verified_capture_inventory': snapshot[0], 'verified_journal_inventory': snapshot[1]}
                    self.save(self.destination/(item+'-native-attribution.json'), record, True)
                    self.prior_captures, self.prior_journal = snapshot
                    self.completed_runs.append(run)
                    return
                stable = snapshot
            except Pending as error:
                last = error.code; stable = None
            time.sleep(min(.05, max(0, until - time.time())))
        raise CaptureError(last)

    def finish(self):
        rows, jobs, _, active, stopped = journal(self.spec)
        unchanged(self.prior_captures, hashes(inventory(self.spec['capture'])))
        if hashes(inventory(self.spec['capture'])) != self.prior_captures or hashes(rows) != self.prior_journal or active is not None or stopped:
            fail('unexpected_final_activity')


def verify_shutdown(spec, destination, save):
    rows, jobs, requests, active, stopped = journal(spec)
    if not stopped or active is not None:
        fail('missing_worker_stop')
    caps = inventory(spec['capture'])
    reconcile_captures(spec, caps, requests)
    previous = sorted(Path(destination).glob('*-native-attribution.json'))
    if jobs:
        last = next((json.loads(p.read_text()) for p in previous if json.loads(p.read_text())['run_id'] == jobs[-1]['run_id']), None)
        if last is None or hashes(caps) != last['verified_capture_inventory']:
            fail('shutdown_unreconciled_capture')
        unchanged(last['verified_journal_inventory'], hashes(rows))
    save(Path(destination)/'observer-shutdown.json', {'worker_stop_verified': True, 'journal': hashes(rows), 'capture': hashes(caps)}, True)
