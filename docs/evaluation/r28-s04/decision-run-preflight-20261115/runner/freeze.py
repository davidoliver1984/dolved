"""Freeze executable inputs only, then evidence. No authority creation."""
import hashlib,json
from pathlib import Path
ROOT=Path('/workspace');HERE=Path(__file__).resolve().parent
def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def manifest(paths):return ''.join(f'{sha(p)}  {p.relative_to(ROOT)}\n' for p in sorted(set(paths)))
paths=[ROOT/l.split('  ',1)[1] for l in (HERE.parent/'mechanical/harness.sha256').read_text().splitlines()]
paths += [HERE/n for n in ('runner.py','accounting.py','run_once.sh')]
(HERE/'executable.sha256').write_text(manifest(paths))
identity={'parent_commit':'b98569487046d1649003ccffa429c90678482c1c','frozen_base_harness_sha256':sha(HERE.parent/'mechanical/harness.sha256'),
 'executable_harness_sha256':sha(HERE/'executable.sha256'),'runtime_sha256':sha(HERE.parent/'runtime-source.sha256'),
 'dependencies_sha256':sha(HERE.parent/'mechanical/dependencies.sha256'),
 'reviewer_confirmation_sha256':sha(ROOT/'docs/evaluation/r28-s04/decision-run-20261115/reviewer-predispatch-confirmation.md'),
 'reservation_usd':'136.737303','hard_stop_usd':'273.474606','status':'provider_free_only_pending_runner_review','live_authority_created':False}
(HERE/'identity.json').write_text(json.dumps(identity,indent=2)+'\n')
(HERE/'checksums.sha256').write_text(manifest(p for p in HERE.iterdir() if p.is_file() and p.name!='checksums.sha256'))
print(json.dumps(identity,indent=2))
