"""Retained service-receipt accounting, with full reservation for uncertain liability."""
import json
import os
from decimal import Decimal
from pathlib import Path

D = Decimal
CAP = D('273.474606')
APPROVED = D('136.737303')


def atomic(path, value):
    path = Path(path)
    tmp = path.with_suffix(path.suffix + '.new')
    with tmp.open('x') as stream:
        json.dump(value, stream, indent=2, allow_nan=False)
        stream.write('\n'); stream.flush(); os.fsync(stream.fileno())
    os.replace(tmp, path)
    fd = os.open(path.parent, os.O_RDONLY)
    try: os.fsync(fd)
    finally: os.close(fd)


def receipts(record):
    # Same service-call accounting unit as C2 accounting.py; do not add parent
    # run/generation aggregates or nested attempt receipts to these amounts.
    rows = []
    calls = list(record.get('service_calls', []))
    for stage, data in record.get('production_trace', {}).items():
        if stage.endswith('.reranked') and isinstance(data, dict):
            # Only supplemental observer usage where no rerank service receipt
            # exists. Otherwise preserve it in the raw trace, not the subtotal.
            if not any(c.get('path', '').endswith('/rerank') for c in calls):
                calls.append({'key': stage, 'path': 'observer:' + stage, 'usage': data.get('usage')})
    for call in calls:
        entries = call.get('usage')
        for u in entries if isinstance(entries, list) else [entries]:
            amount = basis = None
            if isinstance(u, dict):
                amount = u.get('cost_usd', u.get('estimated_cost_usd'))
                basis = u.get('cost_basis')
                if amount is None and u.get('model') == 'gpt-5-mini' and all(type(u.get(k)) is int and u[k] >= 0 for k in ('input_tokens', 'output_tokens')):
                    amount = (D(u['input_tokens']) * D('.25') + D(u['output_tokens']) * 2) / 1000000
                    basis = 'retained_2026-08-19_uncached_rate_estimate'
            if amount is not None:
                amount = D(str(amount))
                if not amount.is_finite() or amount < 0: raise ValueError('invalid_usage_amount')
            rows.append({'call_key': call.get('key'), 'path': call.get('path'), 'receipt': u,
                         'known_or_estimated_usd': None if amount is None else str(amount), 'basis': basis,
                         'missing_cost': amount is None})
    return rows


class Accounting:
    def __init__(self, path, reservation):
        self.path = Path(path)
        self.maximum = D(str(reservation['reservation_usd'])) / D(reservation['items'])
        if reservation['items'] != 34 or D(str(reservation['reservation_usd'])) > APPROVED:
            raise ValueError('reservation_mismatch')
        self.state = json.loads(self.path.read_text()) if self.path.exists() else {'items': {}, 'cap_usd': str(CAP)}
        if self.state['cap_usd'] != str(CAP): raise ValueError('ledger_cap_mismatch')

    def total(self):
        return sum((D(r['charged_liability_usd']) for r in self.state['items'].values()), D(0))

    def reserve(self, item):
        if item in self.state['items']: raise ValueError('prior_accounting_item')
        total = self.total()
        if total + self.maximum > CAP: raise ValueError('budget_exhausted_before_dispatch')
        self.state['items'][item] = {'status': 'reserved', 'maximum_usd': str(self.maximum),
            'charged_liability_usd': str(self.maximum), 'known_or_estimated_usd': '0',
            'unknown_liability_usd': str(self.maximum), 'reason': 'pending_or_interrupted_full_attempt_liability'}
        self.save()

    def settle(self, item, record):
        row = self.state['items'][item]
        if row['status'] != 'reserved': raise ValueError('duplicate_settlement')
        observations = receipts(record)
        known = sum((D(r['known_or_estimated_usd']) for r in observations if r['known_or_estimated_usd'] is not None), D(0))
        # Receipts do not prove absence of unreported calls/retries. Never release
        # their remaining reservation on a successful terminal status alone.
        charged = max(self.maximum, known)
        row.update(status='recorded', known_or_estimated_usd=str(known), charged_liability_usd=str(charged),
                   unknown_liability_usd=str(max(D(0), charged-known)), receipts=observations,
                   reason='full_reserve_retained_no_complete_invoice_or_attempt_coverage_proof')
        self.save()
        if charged > self.maximum: raise ValueError('item_reservation_exceeded')
        if self.total() > CAP: raise ValueError('budget_exhausted')

    def save(self):
        self.state['conservative_committed_usd'] = str(self.total())
        self.state['remaining_to_hard_stop_usd'] = str(CAP-self.total())
        self.state['per_item_maximum_usd'] = str(self.maximum)
        self.state['complete_invoice_total_available'] = False
        atomic(self.path, self.state)
