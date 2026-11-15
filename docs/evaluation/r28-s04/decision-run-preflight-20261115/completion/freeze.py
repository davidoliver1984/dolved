import hashlib
import json
from pathlib import Path
ROOT=Path('/workspace')
HERE=Path(__file__).resolve().parent
def manifest(paths):
    return ''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(ROOT)}\n' for p in sorted(set(paths)))
old=HERE.parent/'harness.sha256'
paths=[ROOT/line.split('  ',1)[1] for line in old.read_text().splitlines()]
paths += [p for p in HERE.iterdir() if p.suffix=='.py']
paths += [HERE/'activation.md', HERE.parent/'capture.override.yaml']
(HERE/'harness.sha256').write_text(manifest(paths))
inputs=[ROOT/p for p in [
 'docs/evaluation/r28-s04/decision-run-20261115/released-input/questions-only.json',
 'docs/evaluation/r28-s03/run/materialisation-result.json',
 'docs/evaluation/r28-s03/run/verification-evidence.json',
 'apps/web/src/components/ChatWorkspace.tsx',
 'apps/api/app/Http/Resources/ConversationResource.php',
 'apps/api/app/Actions/Conversation/OrchestrateConversationRun.php',
 'apps/api/app/Actions/Generation/PersistGeneratedAnswer.php',
 'apps/api/app/Models/GeneratedAnswer.php',
 'apps/api/app/Support/Generation/GenerationResult.php',
 'apps/api/app/Support/Generation/UnresolvedNamedCoverageDisclosure.php']]
inputs += [HERE.parent/'dependencies.sha256',HERE.parent/'checksums.sha256',HERE.parent/'reservation.json']
(HERE/'inputs.sha256').write_text(manifest(inputs))
identity={'application_commit':'15ce7c041f465a2a312c54b30c1bcd62b9805ee7','preflight_parent':'a5ffd13e5ace0554c8d9073375d1928fd4db2cf5',
 'runtime':hashlib.sha256((HERE.parent/'runtime-source.sha256').read_bytes()).hexdigest(),
 'harness_sha256':hashlib.sha256((HERE/'harness.sha256').read_bytes()).hexdigest(),
 'inputs_sha256':hashlib.sha256((HERE/'inputs.sha256').read_bytes()).hexdigest(),
 'base_dependencies_sha256':hashlib.sha256((HERE.parent/'dependencies.sha256').read_bytes()).hexdigest(),
 'provider_calls':0,'live_authorisations_created_or_consumed':0,'full_suite_reruns':0,
 'status':'findings_complete_phase_b_approval_and_mechanical_prerequisites_outstanding'}
(HERE/'identity.json').write_text(json.dumps(identity,indent=2)+'\n')
files=[p for p in HERE.iterdir() if p.is_file() and p.name!='checksums.sha256']
(HERE/'checksums.sha256').write_text(manifest(files))
print(json.dumps(identity,indent=2))
