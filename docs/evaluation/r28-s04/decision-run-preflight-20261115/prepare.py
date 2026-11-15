"""Provider-free preparation from committed sources; never reads reviewer inputs."""
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path('/workspace')
HERE = Path(__file__).resolve().parent
BASE = '15ce7c041f465a2a312c54b30c1bcd62b9805ee7'
OLD = 'docs/evaluation/r28-s04/block1-remaining-verification-20261114/run/harness'
def committed(path):
    return subprocess.check_output(['git', '-c', 'safe.directory=/workspace', 'show', BASE+':'+path], cwd=ROOT)

(HERE/'harness').mkdir(exist_ok=True)
(HERE/'verification').mkdir(exist_ok=True)
for name in ('C1Capture.php', 'dispatch.php', 'preflight.php', 'verify_persistence.php', 'check_record.py', 'test_stop_logic.py'):
    content = committed(OLD+'/'+name).decode()
    if name == 'C1Capture.php':
        content = content.replace("'rejected_planner_output'=>'unavailable','calendar_normalisation_count'=>'unavailable'", "'rejected_planner_output'=>'evaluation_sidecar_if_enabled','calendar_normalisation_count'=>'evaluation_sidecar_if_enabled'")
        content = content.replace("'review_metadata'=>", "'coverage_failure'=>str_ends_with($this->calls[$key]['path'],'/coverage-selection')?($body['detail']['code']??null):null,\n                'review_metadata'=>")
    if name == 'check_record.py':
        content = content.replace("ROOT = Path('/workspace/docs/evaluation/r28-s04/block1-remaining-verification-20261114/run')", "ROOT = Path('/workspace/docs/evaluation/r28-s04/decision-run-preflight-20261115')")
        content = content.replace("    calls = r.get('service_calls', [])", """    coverage = (r.get('retrieval') or {}).get('lineage', {}).get('coverage_selection') or {}
    if coverage.get('run_stop_required') is True:
        return 'stop', 'protected:coverage_selection'
    if coverage.get('status') == 'rejected' and (coverage.get('run_stop_required') is not False
            or coverage.get('proposal_consumed') is not False
            or coverage.get('reason') not in ('invalid_output','incomplete_output','coverage_aspect_provenance',
                'coverage_comparison_side_missing','coverage_need_omitted','coverage_proposal_bounds','coverage_support_provenance')):
        return 'stop', 'unknown_or_integrity:coverage_selection'
    calls = r.get('service_calls', [])""")
        content = content.replace("        if status != 200:", """        if status != 200:
            if (c.get('path','').endswith('/coverage-selection') and status == 503
                    and c.get('coverage_failure') in ('invalid_output','incomplete_output')
                    and coverage.get('run_stop_required') is False
                    and coverage.get('proposal_consumed') is False
                    and coverage.get('reason') == c.get('coverage_failure')):
                continue""")
    (HERE/'harness'/name).write_text(content)

# Reuse current development orchestration fixture, inserting observation only.
s = committed('apps/api/tests/Feature/EvidenceNeedsRetrievalTest.php').decode()
s = s.replace('final class EvidenceNeedsRetrievalTest extends TestCase', 'final class DecisionCaptureFixtureTest extends TestCase')
start = s.index('    public function test_c1_wrong_current_plan_preserves_dated_alternative_and_persisted_answer_use')
end = s.index('    private function familyReviewResponse', start)
part = s[start:end]
old = '        app(OrchestrateConversationRun::class)->handle($run->id);'
assert part.count(old) == 1
part = part.replace(old, '''        require_once __DIR__.'/C1Capture.php';
        $capture = new \\C1Capture('/tmp/phase-a-capture-'.$case.'-'.uniqid().'.ndjson');
        $capture->attachHttp();
        app(OrchestrateConversationRun::class)->handle($run->id, $capture->observe(...));
        $record = $capture->snapshot($run);
        file_put_contents('/workspace/docs/evaluation/r28-s04/decision-run-preflight-20261115/verification/capture-'.$case.'.json', json_encode($record, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        $this->assertNotEmpty($record['observed_temporal_targets']);
        $this->assertNotEmpty($record['generation_request']);
        $this->assertNotEmpty($record['final_allocated_evidence']);
        $this->assertSame($record['temporal_context'], $record['reloaded_temporal_context']);
        $this->assertSame($record['displayed_text'], $record['reloaded_rendered_text']);
''')
s = s[:start]+part+s[end:]
(HERE/'harness/DecisionCaptureFixtureTest.php').write_text(s)
manifest = committed('docs/evaluation/r28-s04/block1-remaining-verification-20261114/temporal-meaning-20261115/runtime-source.sha256')
assert hashlib.sha256(manifest).hexdigest() == 'b73d054d9492e909cfaeadb642544950cfe940de10bfcbe12b388b089997b901'
(HERE/'runtime-source.sha256').write_bytes(manifest)
paths = []
for line in manifest.decode().splitlines():
    digest, path = line.split('  ', 1)
    assert hashlib.sha256((ROOT/path).read_bytes()).hexdigest() == digest, path
    paths.append(path)
tracked = subprocess.check_output(['git', '-c', 'safe.directory=/workspace', 'ls-files', '--', 'apps/api/app', 'apps/api/config', 'apps/api/routes', 'apps/ai/app', 'contracts'], cwd=ROOT).decode().splitlines()
assert sorted(tracked) == sorted(paths)
for service, prefix in [('api','apps/api/'), ('ai','apps/ai/')]:
    rows = []
    for line in manifest.decode().splitlines():
        digest, path = line.split('  ',1)
        if path.startswith(prefix): rows.append(digest+'  /app/'+path[len(prefix):])
        elif path.startswith('contracts/'): rows.append(digest+'  /'+path)
    (HERE/f'{service}-mounted.sha256').write_text('\n'.join(rows)+'\n')
print(json.dumps({'base':BASE,'runtime_files':len(paths),'runtime_verified':True,'inputs_accessed':False,'provider_calls':0}))
