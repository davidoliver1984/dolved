from pathlib import Path
root=Path('/workspace')
here=root/'docs/evaluation/r28-s04/block1-c1-live-20261114/continuation/harness'
s=(root/'apps/api/tests/Feature/EvidenceNeedsRetrievalTest.php').read_text()
s=s.replace('final class EvidenceNeedsRetrievalTest extends TestCase','final class C1CaptureFixtureTest extends TestCase')
start=s.index('    public function test_c1_wrong_current_plan_preserves_dated_alternative_and_persisted_answer_use')
end=s.index("    #[DataProvider('crossServiceCases')]",start)
part=s[start:end]
old='        app(OrchestrateConversationRun::class)->handle($run->id);'
assert part.count(old)==1
part=part.replace(old,"""        require_once '/workspace/docs/evaluation/r28-s04/block1-c1-live-20261114/continuation/harness/C1Capture.php';
        $capture=new \\C1Capture('/tmp/c1-capture-'.$case.'.ndjson');
        $capture->attachHttp();
        app(OrchestrateConversationRun::class)->handle($run->id, $capture->observe(...));
        $captured=$capture->snapshot($run);
        $this->assertNotEmpty($captured['observed_temporal_targets']);
        $this->assertTrue($captured['precautionary_target_admitted']);
        $this->assertTrue($captured['answer_used_precautionary_target']);
        $this->assertSame(1,$captured['additional_search_lanes']);
        $this->assertCount(2,$captured['lane_events']);
        $this->assertNotEmpty($captured['final_allocated_evidence'][0]['temporal_target_ids']);
        $this->assertSame($captured['temporal_context'],$captured['reloaded_temporal_context']);
        $this->assertNotEmpty($captured['temporal_context']['uses']);
        $this->assertTrue($captured['temporal_context']['uses'][0]['planner_disagreement']);
        $this->assertNotEmpty($captured['production_trace']['assembled_generation_request']);
        $this->assertCount(4,$captured['service_calls']);
        foreach($captured['service_calls'] as $call) $this->assertSame('received',$call['status']);
        file_put_contents('/workspace/docs/evaluation/r28-s04/block1-c1-live-20261114/continuation/preflight/capture-fixture-'.$case.'.json',json_encode($captured,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\\n");""")
s=s[:start]+part+s[end:]
(here/'C1CaptureFixtureTest.php').write_text(s)
