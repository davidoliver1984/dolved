<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$items = json_decode(file_get_contents('/workspace/docs/evaluation/r28-s04/block1-temporal-assessment-20261113/probes.json'), true, flags: JSON_THROW_ON_ERROR)['items'];
$lineage = ['provider'=>'openai','model'=>'gpt-5-mini','contract_schema_version'=>'evidence-needs-plan-v4',
    'prompt_version'=>'bounded-evidence-needs-v26','adapter_version'=>'structured-chat-needs-v10'];
ksort($lineage);
$lineage['fingerprint'] = hash('sha256',json_encode($lineage,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
$usage = ['stage'=>'planner','provider'=>'openai','model'=>'gpt-5-mini','execution'=>'provider_api',
    'request_count'=>0,'retry_count'=>0,'latency_ms'=>0,'cost_basis'=>'unavailable'];
$rows=[];
foreach($items as $item) {
    App\Support\Retrieval\EvidenceNeedsPlan::validate($item['plan'],$item['user_turns'],'2026-11-13T12:00:00Z');
    $plan = App\Support\Retrieval\EvidenceNeedsPlan::fromArray($item['plan'],$item['user_turns'],$lineage,$usage,'2026-11-13T12:00:00Z');
    $lanes=[];
    foreach($item['plan']['needs'] as $need) {
        $r=$plan->retrievalPlan($need['need_id']);
        $lanes[]=['need_id'=>$need['need_id'],'mode'=>$r->temporalMode->value,'date'=>$r->explicitDate?->toDateString(),
            'reference'=>$r->temporalReference,'fact_date'=>$r->factDate,'query'=>$r->query];
    }
    $rows[]=['probe_id'=>$item['probe_id'],'valid'=>true,'lanes'=>$lanes];
}
echo json_encode(['provider_calls'=>0,'rows'=>$rows],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
