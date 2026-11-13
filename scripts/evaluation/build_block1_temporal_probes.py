"""Provider-free construction of the reviewer-approved 25-plan experiment.

Run inside AI with /workspace mounted. Refuses to overwrite a frozen selection.
Only authorised ingested-source identities and the reviewer package are read.
"""
import hashlib
import json
from copy import deepcopy
from pathlib import Path

from app.retrieval.needs import CatalogueFamily, EvidenceNeedsPlan, UserTurn

ROOT = Path('/workspace')
BASE = ROOT / 'docs/evaluation/r28-s04'
PACKAGE = BASE / 'block1-temporal-confirmation-package-20261113'
OUT = BASE / 'block1-temporal-assessment-20261113'


def load(path):
    return json.loads(path.read_text())


def span(text):
    return {'turn_id': 'u1', 'quote': text}


def current():
    return {'kind': 'current', 'value': None, 'precision': 'none', 'source': None}


def authority(quote, value, precision='day'):
    return {'kind': 'date' if precision == 'day' else 'period', 'value': value,
            'precision': precision, 'source': span(quote)}


def fact(quote, value, precision='day'):
    return {'value': value, 'precision': precision, 'source': span(quote)}


def main():
    for name, expected in [('selection.json', '530c05066fc7f9db6dd6fa45fef831fa9720a3463efe056f280500d6fad7b207'),
                           ('amendment-1.json', '54b9d9bf7c5a9ffec5dfd934aef1605ed3a234e74f138a636ef8190a1f5b934f')]:
        assert hashlib.sha256((PACKAGE / name).read_bytes()).hexdigest() == expected
    selection = load(PACKAGE / 'selection.json')
    amendment = load(PACKAGE / 'amendment-1.json')
    items = [amendment['superseded_items'].get(i['id'], i) for i in selection['items']]
    sources = load(BASE / 'block1-temporal-package-verification-20261113/ingested-sources-complete.json')['rows']
    by_file = {s['source_filename']: s for s in sources}
    catalogue = tuple(CatalogueFamily(family_id=s['family_id'], title=s['source_filename']) for s in sources)
    prototype = load(ROOT / 'contracts/http/retrieval-needs/v4/examples.json')[0]['plan']
    # Role choices fixed before observing the checker; no model authored these.
    specs = [
        ('maintenance-contractor-schedule-v1.pdf', '14 October 2025', '2025-10-14', 'day', 'historical'),
        ('serious-incident-review-v1.pdf', 'December 2025', '2025-12', 'month', 'historical'),
        ('email-retention-policy-v1.pdf', '20 November 2025', '2025-11-20', 'day', 'historical'),
        ('staff-reference-request-v2.pdf', '30 September 2025', '2025-09-30', 'day', 'current'),
        ('falls-prevention-policy-v2.docx', '2 February 2026', '2026-02-02', 'day', 'current'),
        ('serious-incident-review-v2.pdf', 'September 2025', '2025-09', 'month', 'current'),
        ('staff-benefits-guidance-v2.txt', 'May 2025', '2025-05', 'month', 'current'),
        ('maintenance-contractor-schedule-v1.pdf', '5 December 2025', '2025-12-05', 'day', 'both'),
        ('staff-reference-request-v2.pdf', '2 July 2026', '2026-07-02', 'day', 'distinct'),
        ('email-retention-policy-v1.pdf', '31 December 2025', '2025-12-31', 'day', 'compare'),
        ('missing-person-procedure-v1.pdf', 'When my mum went missing from the home', None, 'named', 'unknown'),
        ('staff-benefits-guidance-v1.txt', '1 June 2025', '2025-06-01', 'day', 'conflict'),
    ]
    probes, expectations = [], []
    for item, (filename, quote, value, precision, shape) in zip(items, specs, strict=True):
        plan = deepcopy(prototype)
        need = deepcopy(plan['needs'][1])
        need.update(need_id='n1', aspect=item['utterance'], question_span=span(item['utterance']),
                    supports_need_id=None, authority=current(), fact_reference=None)
        if shape in {'historical', 'both', 'distinct', 'compare', 'conflict'}:
            need['authority'] = authority(quote, value, precision)
        if shape in {'current', 'both'}:
            need['fact_reference'] = fact(quote, value, precision)
        if shape == 'distinct':
            need['fact_reference'] = fact('15 March 2025', '2025-03-15')
        if shape in {'compare', 'conflict', 'unknown'}:
            need['operation'] = 'compare'
        if shape == 'unknown':
            need['authority'] = {'kind': 'previous', 'value': quote, 'precision': 'named', 'source': span(quote)}
        plan['needs'] = [need]
        plan['bindings'] = [{'need_id': 'n1', 'subject_status': 'matched',
                             'family_ids': [by_file[filename]['family_id']],
                             'ownership': 'authorised_workspace', 'ownership_source': None}]
        wrong = deepcopy(plan)
        w = wrong['needs'][0]
        if shape in {'historical', 'both'}:
            w.update(authority=current(), fact_reference=fact(quote, value, precision))
            why = 'The requested historical rule becomes current; the date remains fact context only.'
        elif shape == 'current':
            w['authority'] = authority(quote, value, precision)
            # Keeping the event date as fact context is coherent: the error is
            # selecting that date for the current rule as well.
            why = 'The old event date selects historical authority despite an explicit current question.'
        elif shape == 'distinct':
            w['authority'] = authority('15 March 2025', '2025-03-15')
            w['fact_reference'] = fact('2 July 2026', '2026-07-02')
            why = 'The leaving date wrongly selects authority; the request date is reduced to fact context. Leaving predates the first version.'
        else:
            w.update(operation='retrieve', authority=current())
            if value is not None:
                w['fact_reference'] = fact(quote, value, precision)
            why = 'Only current authority executes; the requested historical/unknown or conflicting selector is silently lost, even though the full question is copied.'
        for label, candidate in [('correct', plan), ('wrong', wrong)]:
            turns = (UserTurn(turn_id='u1', text=item['utterance']),)
            checked = EvidenceNeedsPlan.model_validate(candidate)
            checked.validate_grounding(turns, evaluated_at='2026-11-13T12:00:00Z')
            checked.validate_catalogue(catalogue, turns)
            probes.append({'probe_id': item['id']+'-'+label,
                           'user_turns': [t.model_dump() for t in turns],
                           'plan': checked.execution_plan().model_dump(mode='json')})
        expectations.append({'id': item['id'], 'shape': item['shape'], 'wrong_reason': why,
                             'transformation': {'before': plan['needs'][0], 'after': wrong['needs'][0]},
                             'correct_framing': 'Explicitly qualified two-version alternative, not a claim that either applied to the unknown event.' if shape == 'unknown' else 'Preserve every requested temporal side.',
                             'source_expectation': item})
    retained = load(BASE / 'r06-governed-evidence/R28-R07-BLOCK1-EXTRACTIVE-FINAL-20261113-01/cases/01-v4.case.corrected-b03-07-v1.json')
    checks = retained['observation']['usage']['planner_validation_attempts']
    # Extract the actual accepted checker input, not a reconstructed failure.
    retained_plan = next(x['interpretation_check']['reviewed_plan'] for x in checks if x.get('interpretation_check', {}).get('code') == 'accepted')
    turns = (UserTurn(turn_id='u1', text=selection['development_case']['utterance']),)
    # Preserve original turn identities from the retained plan.
    turns = (UserTurn(turn_id=retained_plan['needs'][0]['question_span']['turn_id'], text=selection['development_case']['utterance']),)
    checked = EvidenceNeedsPlan.model_validate(retained_plan)
    checked.validate_grounding(turns)
    probes.append({'probe_id': 'input16-development-wrong', 'user_turns': [t.model_dump() for t in turns], 'plan': retained_plan})
    expectations.append({'id': 'input16-development', 'shape': 'retained_historical_date_as_fact',
                         'wrong_reason': 'February supplier question executed current authority; its February date was only fact_reference.',
                         'development': True})
    assert len(probes) == 25
    for name, body in [('probes.json', {'items': probes}), ('expectations.json', {'items': expectations,
        'attempts_per_plan': 5, 'correct_attempts': 60, 'wrong_attempts': 65,
        'minimum_appropriate_verified_wrong_rejections': 59, 'maximum_correct_false_rejections': 3,
        'all_patterns_must_have_a_catch': True, 'invalid_or_incomplete_cannot_pass': True})]:
        with (OUT / name).open('x') as handle:
            json.dump(body, handle, indent=2)
            handle.write('\n')


if __name__ == '__main__':
    main()
