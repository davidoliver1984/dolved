"""Checksum and identity verification only; never dispatches a provider call."""
import hashlib
import json
from pathlib import Path
import subprocess

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[3]
assert json.loads((OUT/'freeze-before.json').read_text()) == json.loads((OUT/'freeze-after.json').read_text())
records = list((OUT/'results').glob('*.json'))
assert len(records) == 125
assert len((OUT/'dispatch.jsonl').read_text().splitlines()) == 125
remote = subprocess.check_output(['git','ls-remote','origin','refs/heads/main','refs/heads/codex/block1-extractive-hold-20261112'],cwd=ROOT,text=True)
assert '348f09e053c8ad73676921c5c616cced812111d7\trefs/heads/main' in remote
with (OUT/'remote-state-before-evidence-commit.json').open('x') as handle:
    json.dump({'refs':remote.splitlines(),'application_commit':'896717a30c26d3f3ea6d48df8684e1d8af95c090','main_unchanged':True},handle,indent=2)
paths = sorted(p for p in OUT.rglob('*') if p.is_file() and p.name != 'checksums.sha256')
(OUT/'checksums.sha256').write_text(''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(OUT)}\n' for p in paths))
for line in (OUT/'checksums.sha256').read_text().splitlines():
    digest,name=line.split('  ',1)
    assert hashlib.sha256((OUT/name).read_bytes()).hexdigest()==digest
print(f'{len(paths)}/{len(paths)} checksums verified; 125 dispatches/records; freeze identical; main unchanged')
