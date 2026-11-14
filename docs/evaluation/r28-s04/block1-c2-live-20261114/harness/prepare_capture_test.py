from pathlib import Path
root=Path('/workspace')
here=root/'docs/evaluation/r28-s04/block1-c2-live-20261114/harness'
s=(root/'apps/api/tests/Feature/EvidenceNeedsRetrievalTest.php').read_text()
s=s.replace('final class EvidenceNeedsRetrievalTest extends TestCase','final class C1CaptureFixtureTest extends TestCase')
start=s.index('    public function test_c1_wrong_current_plan_preserves_dated_alternative_and_persisted_answer_use')
end=s.index('    private function familyReviewResponse',start)
part=s[start:end]
old='        app(OrchestrateConversationRun::class)->handle($run->id);'
assert part.count(old)==1
part=part.replace(old,"""        require_once '/workspace/docs/evaluation/r28-s04/block1-c2-live-20261114/harness/C1Capture.php';
        $capture=new \\C1Capture('/tmp/c2-capture-'.$case.'.ndjson');
        $capture->attachHttp();
        app(OrchestrateConversationRun::class)->handle($run->id, $capture->observe(...));
        $captured=$capture->snapshot($run);
        $this->assertNotEmpty($captured['observed_temporal_targets']);
        $this->assertTrue($captured['precautionary_target_admitted']);
        $this->assertTrue($captured['answer_used_precautionary_target']);
        $this->assertSame(1,$captured['additional_search_lanes']);
        $this->assertCount($c2 ? 1 : 2,$captured['lane_events']); // C2 reuses current discovery evidence.
        $this->assertNotEmpty($captured['final_allocated_evidence'][0]['temporal_target_ids']);
        $this->assertSame($captured['temporal_context'],$captured['reloaded_temporal_context']);
        $this->assertNotEmpty($captured['temporal_context']['uses']);
        $this->assertTrue($captured['temporal_context']['uses'][0]['planner_disagreement']);
        $this->assertNotEmpty($captured['production_trace']['assembled_generation_request']);
        $this->assertCount($c2 ? 5 : 4,$captured['service_calls']);
        if($c2) {
            $this->assertSame('C2-resolved',$captured['routes_by_need']['n1']['route']);
            $this->assertSame('selected',$captured['routes_by_need']['n1']['selection']['decision']);
            $this->assertSame('equivalent',$captured['routes_by_need']['n1']['discovery_work']['current_reuse']);
        }
        foreach($captured['service_calls'] as $call) $this->assertSame('received',$call['status']);
        file_put_contents('/workspace/docs/evaluation/r28-s04/block1-c2-live-20261114/preflight/capture-fixture-'.$case.'.json',json_encode($captured,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\\n");""")
s=s[:start]+part+s[end:]
(here/'C1CaptureFixtureTest.php').write_text(s)
