"""Seal this provider-free package only; no execution or prior-evidence writes."""
import hashlib
import json
from datetime import UTC, datetime
from pathlib import Path

HERE = Path(__file__).resolve().parent
start = datetime(2026, 11, 14, 19, 26, 21, tzinfo=UTC)
end = datetime.now(UTC)
(HERE / 'work-accounting.json').write_text(json.dumps({
    'first_recorded_clock_after_initial_inspection': start.isoformat(),
    'evidence_sealing_time': end.isoformat(),
    'measured_elapsed_seconds': (end-start).total_seconds(),
    'includes': 'implementation, provider-free testing, replay, documentation through evidence sealing',
    'not_included': 'initial inspection before first clock observation; publication after sealing',
    'focused_working_day_conversion': 'unavailable; definition not recorded',
    'cap_activation': 'not activated in the private record',
    'approved_cap_start_deadline_remaining_allowance': 'not recorded; not inferred',
    'paid_provider_calls': 0,
}, indent=2) + '\n')
paths = [p for p in HERE.rglob('*') if p.is_file() and '__pycache__' not in p.parts
         and p.name not in {'checksums.sha256', 'aggregate.sha256'}]
manifest = ''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(HERE)}\n' for p in sorted(paths))
(HERE/'checksums.sha256').write_text(manifest)
(HERE/'aggregate.sha256').write_text(hashlib.sha256(manifest.encode()).hexdigest()+'  checksums.sha256\n')
print(json.dumps({'files':len(paths), 'aggregate':hashlib.sha256(manifest.encode()).hexdigest(), 'measured_elapsed_seconds':(end-start).total_seconds()}))
