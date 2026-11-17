<?php
declare(strict_types=1);
require '/app/vendor/autoload.php';
require __DIR__.'/worker_observer.php';
function uuidFor(int $n): string { return sprintf('00000000-0000-4000-8000-%012d', $n); }
function verify(bool $ok, string $code): void { if (! $ok) { throw new RuntimeException($code); } }
$root = $argv[1];
$results = [];
foreach (['empty', 'missing', 'null', 'string', 'object', 'successful_empty', 'accepted', 'rejected', 'retry'] as $mode) {
    $dir = $root.'/'.$mode; mkdir($dir); mkdir($dir.'/journal'); mkdir($dir.'/health');
    $events = new Illuminate\Events\Dispatcher(new Illuminate\Container\Container);
    $observer = new EvaluationWorkerObserver(['journal'=>$dir.'/journal','health'=>$dir.'/health','execution_id'=>uuidFor(1),'worker_id'=>uuidFor(2),'workspace_id'=>uuidFor(3),'ai_origin'=>'http://controlled'], fn ($job)=>['run_id'=>uuidFor(10),'workspace_id'=>uuidFor(3),'status'=>'completed']);
    $observer->register($events);
    $job = new class {
        public function getJobId() { return '10'; }
        public function getQueue() { return 'conversation-generation'; }
    };
    $events->dispatch(new Illuminate\Queue\Events\JobProcessing('conversation',$job));
    $http = new Illuminate\Http\Client\Factory($events); $http->preventStrayRequests();
    $responses=[]; $sent=[];
    $http->fake(function ($request) use ($mode, &$responses, &$sent) {
        $id=json_decode($request->body(),true)['request_id']; $sent[]=$request->body();
        $receipt=['category'=>'provider_unavailable','provider_status'=>503,'systemic'=>false,'attempt_count'=>0,'validation_attempts'=>[]];
        if ($mode==='missing') { unset($receipt['validation_attempts']); }
        if ($mode==='null') { $receipt['validation_attempts']=null; }
        if ($mode==='string') { $receipt['validation_attempts']='not-an-array'; }
        if ($mode==='object') { $receipt['validation_attempts']=['unexpected'=>'value']; }
        $status=503;$body=['detail'=>$receipt];
        if (in_array($mode,['accepted','successful_empty'],true) || ($mode==='retry' && count($sent)===2)) {
            $status=200;$body=['request_id'=>$id,'usage'=>['validation_attempts'=>$mode==='successful_empty'?[]:[['attempt'=>1,'status'=>'accepted']]]];
        }
        if ($mode==='rejected') { $body['detail']['validation_attempts']=[['attempt'=>1,'status'=>'rejected']];$body['detail']['attempt_count']=1; }
        $responses[]=[$status,$body];
        return Illuminate\Http\Client\Factory::response($body,$status);
    });
    for($i=0;$i<($mode==='retry'?2:1);$i++) {
        $body=json_encode(['workspace_id'=>uuidFor(3),'request_id'=>uuidFor(100+$i)]);
        $response=$http->withBody($body,'application/json')->post('http://controlled/api/internal/retrieval/plan-needs');
        verify($sent[$i]===$body && [$response->status(),$response->json()]===$responses[$i],'request/response unchanged');
    }
    $events->dispatch(new Illuminate\Queue\Events\JobProcessed('conversation',$job));
    $events->dispatch(new Illuminate\Queue\Events\WorkerStopping);
    $healthy=!is_file($dir.'/health/observer-failed');
    $expected=!in_array($mode,['null','string','object','successful_empty'],true);
    verify($healthy===$expected,'health '.$mode);
    $rows=array_map(fn($p)=>json_decode(file_get_contents($p),true),glob($dir.'/journal/*.json'));
    $ends=array_values(array_filter($rows,fn($r)=>$r['kind']==='request_end'));
    if(in_array($mode,['empty','retry'],true)) { verify($ends[0]['attempts']===[] && $ends[0]['attempt_receipt_state']==='observed_empty' && $ends[0]['failure_category']==='provider_unavailable' && $ends[0]['provider_status']===503 && $ends[0]['systemic']===false,'empty observation'); }
    if($mode==='missing') { verify($ends[0]['attempts']===null && $ends[0]['attempt_receipt_state']==='missing','missing distinct'); }
    $results[$mode]=['healthy'=>$healthy,'request_count'=>count($sent)];
}
echo json_encode($results)."\n";
