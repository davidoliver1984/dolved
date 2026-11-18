<?php
declare(strict_types=1);
require '/app/vendor/autoload.php';
require __DIR__.'/worker_observer.php';
require __DIR__.'/rehearsal_isolation.php';
$root=$argv[1];mkdir($root.'/journal');mkdir($root.'/health');
$container=new Illuminate\Container\Container;
$events=new Illuminate\Events\Dispatcher($container);
$observer=new EvaluationWorkerObserver(['journal'=>$root.'/journal','health'=>$root.'/health','execution_id'=>'00000000-0000-4000-8000-000000000001','worker_id'=>'00000000-0000-4000-8000-000000000002','workspace_id'=>'00000000-0000-4000-8000-000000000003'],fn()=>throw new RuntimeException('job forbidden'));
$observer->register($events);
$loops=0;
$events->listen(Illuminate\Queue\Events\Looping::class,function()use(&$loops){
    ++$loops;
    if (! (pcntl_signal_get_handler(SIGTERM) instanceof Closure)) { throw new RuntimeException('signal not installed'); }
    if($loops===2) { posix_kill(getmypid(),SIGTERM); }
});
$http=new Illuminate\Http\Client\Factory($events);
installEmptyQueueRehearsal($events,$http);
$blocked=false;
try{$http->get('https://not-dispatched.invalid/');}catch(Illuminate\Http\Client\StrayRequestException){$blocked=true;}
if(!$blocked)throw new RuntimeException('outbound guard absent');
$queue=Mockery::mock(Illuminate\Contracts\Queue\Factory::class);
$queue->shouldNotReceive('connection'); // A new queued job cannot even be polled.
$exceptions=Mockery::mock(Illuminate\Contracts\Debug\ExceptionHandler::class);
$exceptions->shouldNotReceive('report');
$worker=new Illuminate\Queue\Worker($queue,$events,$exceptions,fn()=>false);
$worker->setCache(new Illuminate\Cache\Repository(new Illuminate\Cache\ArrayStore));
$status=$worker->daemon('conversation','conversation-generation',new Illuminate\Queue\WorkerOptions(sleep:0));
$rows=array_map(fn($p)=>json_decode(file_get_contents($p),true),glob($root.'/journal/*.json'));
if($status!==0||array_column($rows,'kind')!==['worker_start','worker_loop_ready','worker_stop']||count(glob($root.'/health/*'))!==0)throw new RuntimeException('lifecycle mismatch');
Mockery::close();
echo json_encode(['exit_code'=>$status,'loops'=>$loops,'queue_connections'=>0,'job_reservations'=>0,'http_guard_blocked_controlled_attempt'=>$blocked,'kinds'=>array_column($rows,'kind')])."\n";
