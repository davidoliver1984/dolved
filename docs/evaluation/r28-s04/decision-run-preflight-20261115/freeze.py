import hashlib
import json
import subprocess
from pathlib import Path
ROOT=Path('/workspace')
HERE=Path(__file__).resolve().parent
def manifest(paths):
    return ''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(ROOT)}\n' for p in sorted(paths))
harness=[p for p in (HERE/'harness').iterdir() if p.is_file()]
harness += [HERE/n for n in ('prepare.py','observe.php','index_check.py','reservation.py','capture.override.yaml','freeze.py')]
(HERE/'harness.sha256').write_text(manifest(harness))
dependencies=[ROOT/p for p in ('compose.yaml','compose.e2e.yaml','compose.r28-s03.yaml','apps/api/composer.lock','apps/ai/uv.lock','apps/api/phpunit.xml','apps/ai/pyproject.toml','apps/api/tests/Feature/EvidenceNeedsRetrievalTest.php','apps/ai/tests/test_evaluation_capture.py','apps/ai/tests/test_coverage_selection.py','apps/api/tests/Feature/CoverageSelectionClientTest.php','apps/api/tests/Unit/CoverageAllocationTest.php')]
dependencies += [HERE/'verification'/p for p in ('api-installed.sha256','ai-installed.txt','deployment.txt')]
(HERE/'dependencies.sha256').write_text(manifest(dependencies))
identity={'application_commit':'15ce7c041f465a2a312c54b30c1bcd62b9805ee7',
 'runtime':hashlib.sha256((HERE/'runtime-source.sha256').read_bytes()).hexdigest(),
 'harness_sha256':hashlib.sha256((HERE/'harness.sha256').read_bytes()).hexdigest(),
 'dependencies_sha256':hashlib.sha256((HERE/'dependencies.sha256').read_bytes()).hexdigest(),
 'main':subprocess.check_output(['git','-c','safe.directory=/workspace','rev-parse','main'],cwd=ROOT).decode().strip(),
 'status':'phase_a_not_cleared','provider_calls':0,'authorisations_created_or_consumed':0,'input_files_accessed':0}
(HERE/'identity.json').write_text(json.dumps(identity,indent=2)+'\n')
files=[p for p in HERE.rglob('*') if p.is_file() and '__pycache__' not in str(p) and p.name!='checksums.sha256']
(HERE/'checksums.sha256').write_text(manifest(files))
print(json.dumps(identity,indent=2))
