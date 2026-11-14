"""Non-grading checkpoint safety gate. Unknown failures stop, not pass."""
import hashlib
import json
import sys
from pathlib import Path

ROOT = Path('/workspace/docs/evaluation/r28-s04/block1-c2-live-20261114')

def disposition(r):
    calls = r.get('service_calls', [])
    for c in calls:
        if c.get('status') != 'received':
            return 'stop', 'infrastructure:service_not_received'
        status = c.get('http_status')
        if status in (401, 403):
            return 'stop', 'authorisation:http_status'
        if status != 200:
            # Explicit producer attribution, not a guess from the generic 503.
            if not (c.get('path', '').endswith('/compare-family') and status == 503
                    and c.get('comparison_review_failure') == 'invalid_output'):
                return 'stop', 'infrastructure_or_ambiguous:http_failure'
    usage = r.get('usage') or {}
    retrieval = r.get('retrieval') or {}
    lineage = retrieval.get('lineage') or {}
    failure = lineage.get('failure') or {}
    if failure:
        if (failure.get('category') == 'contract_validation_error'
                and failure.get('stage') == 'transport_orchestration'
                and failure.get('execution') == 'provider_api'
                and failure.get('provider') == 'openai'
                and any(c.get('comparison_review_failure') == 'invalid_output' for c in calls)):
            return 'continue', 'recorded_model_contract_rejection:family_review'
        return 'stop', 'protected_or_ambiguous:retrieval_failure'
    if retrieval.get('clarification_reason') in ('deadline_exhausted', 'active_corpus_changed'):
        return 'stop', 'budget_or_integrity:retrieval_reason'
    if r.get('status') == 'failed':
        pf = usage.get('planner_failure') or {}
        if (pf.get('category') == 'invalid_grounded_needs_plan'
                and pf.get('provider_status') == 200 and pf.get('systemic') is False):
            return 'continue', 'recorded_model_contract_rejection:planner'
        gf = usage.get('generation') or {}
        if gf.get('category') == 'contract_validation_failure' and gf.get('provider_status') == 200:
            return 'continue', 'recorded_model_contract_rejection:generation'
        return 'stop', 'protected_or_ambiguous:unclassified_failed_record'
    if r.get('status') not in ('completed', 'clarification_required', 'retrieval_no_answer'):
        return 'stop', 'integrity:unexpected_status'
    return 'continue', 'recorded_production_outcome_not_graded'

def check(item):
    r = json.loads((ROOT/'cases'/f'{item}.json').read_text())
    previous = ''
    for number, line in enumerate((ROOT/'cases'/f'{item}-ledger.ndjson').read_text().splitlines(), 1):
        event = json.loads(line)
        unhashed = line.rsplit(',"hash":', 1)[0] + '}'
        assert event['sequence'] == number and event['previous_hash'] == previous
        assert hashlib.sha256(unhashed.encode()).hexdigest() == event['hash']
        previous = event['hash']
    assert r['input'] == json.loads((ROOT/'inputs'/f'{item}.json').read_text())
    assert r['temporal_context'] == r['reloaded_temporal_context'] or (not r['temporal_context'] and r['reloaded_temporal_context'] is None)
    action, reason = disposition(r)
    verdict = {'item': item, 'action': action, 'reason': reason, 'status': r['status'], 'grading': False}
    (ROOT/'cases'/f'{item}.verdict.json').write_text(json.dumps(verdict, indent=2)+'\n')
    print(json.dumps(verdict))
    if action == 'stop': raise SystemExit(4)

if __name__ == '__main__': check(sys.argv[1])
