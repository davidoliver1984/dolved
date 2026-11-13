"""Offline accounting of the completed fixed experiment; no model judge.

The implementer reviewed every verified rejection against its frozen plan pair,
the PHP execution mapping and cited question/field. These rationale mappings
record that review; the code aggregates it, not infer entailment from a citation.
"""
import hashlib
import json
import statistics
from collections import Counter, defaultdict
from pathlib import Path

OUT = Path(__file__).resolve().parent
load = lambda p: json.loads(p.read_text())
expected = {x['id']: x for x in load(OUT/'expectations.json')['items']}
false_notes = {
    'T02': 'December2025 correctly selects the rule period; the checker instead treats it as fact context.',
    'T04': 'The reference was just requested, so current authority is correct; the leaving date remains event context.',
    'T08': 'The executable request retains 90 minutes and the dated authority/fact roles. No additional execution lane or typed duration is needed for the requested source comparison.',
    'T10': 'PHP compare mode supplies both the dated historical lane and current lane; today is not omitted.',
    'T11': 'The reviewer explicitly approved the qualified two-version alternative. Compare/previous supplies both known versions, not an assertion that the undated event used the previous rule.',
}
rows, by_question, by_pattern = [], defaultdict(Counter), defaultdict(Counter)
for p in sorted((OUT/'results').glob('*.json')):
    record = load(p)
    receipt = record['receipt']
    correct = record['probe_id'].endswith('-correct')
    qid = record['probe_id'].removesuffix('-correct').removesuffix('-wrong')
    code = receipt['code']
    verified = receipt['citation_verification'] == 'verified'
    if code == 'unavailable':
        verdict = 'unavailable'
        note = 'Provider token usage retained, but no valid checker decision; precise malformed/incomplete/grounding/consistency cause is not retained. No retry.'
    elif correct and code != 'accepted':
        assert qid in false_notes and verified
        verdict, note = 'false_rejection', false_notes[qid]
    elif correct:
        verdict, note = 'correct_acceptance', 'Accepts the reviewed correct executable plan.'
    elif verified and code in {'meaning_changed','clause_omitted','scope_added'}:
        verdict, note = 'appropriate_verified_rejection', expected[qid]['wrong_reason']
    elif receipt['citation_verification'] == 'unverifiable':
        verdict = 'miss_unverifiable_rejection'
        note = 'The cited plan field is not the literal field value; the existing policy keeps the wrong plan. A semantic suspicion without a verified citation is not a catch.'
    else:
        assert qid == 'T12'
        verdict = 'miss_accepted_wrong_plan'
        note = 'The checker labels current as authority and 1June2025 as fact_context, accepting loss of the past selector. This violates the frozen conflict expectation.'
    category = 'correct' if correct else 'wrong'
    counts = {category+'_attempts':1, verdict:1}
    by_question[qid].update(counts)
    by_pattern[expected[qid]['shape']].update(counts)
    rows.append({'record':str(p.relative_to(OUT)), 'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),
                 'probe_id':record['probe_id'], 'expected_plan':category, 'verdict':verdict,
                 'rationale':note, 'checker_code':receipt['checker_code'], 'effective_code':code,
                 'citation_verification':receipt['citation_verification'],
                 'verification_reason':receipt['verification_reason'], 'citation':receipt['citation'],
                 'temporal_assessments':receipt['temporal_assessments']})
assert len(rows) == 125
totals = Counter(r['verdict'] for r in rows)
usage = [load(OUT/r['record'])['receipt'] for r in rows]
assert all(isinstance(u['input_tokens'],int) and isinstance(u['output_tokens'],int) for u in usage)
def distribution(values):
    return {'min':min(values),'mean':statistics.mean(values),'median':statistics.median(values),'max':max(values)}
inputs, outputs = sum(u['input_tokens'] for u in usage), sum(u['output_tokens'] for u in usage)
development = [u for u,r in zip(usage,rows) if r['probe_id']=='input16-development-wrong']
old = load(OUT.parent/'r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-FINAL-20261113-01/cases/01-v4.case.corrected-b03-07-v1.json')
old_check = old['observation']['usage']['planner_validation_attempts'][0]['interpretation_check']
accounting = {'provider_calls':125,'planner_calls':0,'retrieval_calls':0,'generation_calls':0,
    'input_tokens':inputs,'output_tokens':outputs,'cost_usd_retained_rate':inputs*.25/1e6+outputs*2/1e6,
    'reservation_usd':load(OUT/'authorization.json')['reservation_usd'],
    'cost_basis':'Retained gpt-5-mini USD0.25/M input and USD2/M output. No cache discount. Not a billing invoice or independently verified current tariff.',
    'unaccounted_usage_calls':0,
    'input_tokens_per_check':distribution([u['input_tokens'] for u in usage]),
    'output_tokens_per_check':distribution([u['output_tokens'] for u in usage]),
    'latency_ms_per_check':distribution([u['latency_ms'] for u in usage]),
    'development_same_plan':{'old_v2':{k:old_check[k] for k in ['input_tokens','output_tokens','latency_ms']},
        'new_v3':{k:distribution([u[k] for u in development]) for k in ['input_tokens','output_tokens','latency_ms']},
        'limitation':'One retained v2 call versus five new v3 calls, not a contemporaneous paired control. Token delta measured for the same plan; latency/output differences are observational, not causal overhead estimates.'}}
gates = {'wrong_catch':{'count':totals['appropriate_verified_rejection'],'denominator':65,'required_minimum':59},
         'false_rejection':{'count':totals['false_rejection'],'denominator':60,'maximum':3},
         'patterns_with_zero_catches':[key for key,c in by_pattern.items() if c['wrong_attempts'] and not c['appropriate_verified_rejection']],
         'complete_dispatch':len(rows)==125, 'unavailable_checks':totals['unavailable'],
         'disposition':'HOLD; no end-to-end confirmation; no tuning or rerun'}
assert gates['patterns_with_zero_catches']==['conflicting_temporal_information']
for name,value in [('semantic-review.json',{'reviewer':'Codex implementer; source/plan-based review, not independent human adjudication or a model-judge call','totals':dict(totals),'per_question':dict(by_question),'per_pattern':dict(by_pattern),'gates':gates,'attempts':rows}),('accounting.json',accounting)]:
    with (OUT/name).open('x') as handle:
        json.dump(value,handle,indent=2);handle.write('\n')
print(json.dumps({'gates':gates,'accounting':accounting},indent=2))
