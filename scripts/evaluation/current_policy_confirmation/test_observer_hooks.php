<?php

declare(strict_types=1);
require '/app/vendor/autoload.php';
require __DIR__.'/worker_observer.php';

function check(bool $ok, string $message): void { if (! $ok) { throw new RuntimeException($message); } }
function uid(int $n): string { return sprintf('00000000-0000-4000-8000-%012d', $n); }

final class SyntheticObservedJob extends Illuminate\Queue\Jobs\SyncJob
{
    public string $state = 'running';
    public function __construct(public string $id, public string $run, public Closure $action, private $events)
    {
        parent::__construct(new Illuminate\Container\Container, json_encode(['uuid' => uid((int) $id), 'job' => 'synthetic', 'maxTries' => 1]), 'conversation', 'conversation-generation');
    }
    public function getQueue() { return 'conversation-generation'; }
    public function getJobId() { return $this->id; }
    public function fire() { ($this->action)($this); }
    public function fail($e = null) { $this->markAsFailed(); $this->delete(); $this->events->dispatch(new Illuminate\Queue\Events\JobFailed('conversation', $this, $e)); }
}

function scenario(string $root, bool $enabled, string $fault = ''): array
{
    mkdir($root); mkdir($root.'/journal'); mkdir($root.'/health');
    $container = new Illuminate\Container\Container;
    Illuminate\Container\Container::setInstance($container);
    $container->instance('config', new Illuminate\Config\Repository(['retrieval' => ['caller' => [
        'key_id' => 'synthetic-only', 'secret' => base64_encode(str_repeat('synthetic', 8)),
    ]]]));
    $events = new Illuminate\Events\Dispatcher($container);
    $http = new Illuminate\Http\Client\Factory($events);
    $http->preventStrayRequests();
    $sent = []; $returned = []; $requests = 0; $exceptions = [];
    $http->fake(function ($request) use (&$sent, &$requests) {
        $sent[] = [$request->url(), $request->body(), $request->header('X-Retrieval-Caller-Signature')];
        $requests++;
        $body = json_decode($request->body(), true);
        if (str_ends_with($request->url(), '/unrelated')) { return Illuminate\Http\Client\Factory::response(['secret' => 'NEVER_JOURNAL_BODY'], 200); }
        if (($body['fixture'] ?? null) === 'transport') { return Illuminate\Http\Client\Factory::failedConnection('NEVER_JOURNAL_EXCEPTION'); }
        if (str_ends_with($request->url(), '/plan-needs')) {
            $a = match ($body['fixture'] ?? '') {
                'outer_failure' => [['attempt' => 1, 'status' => 'rejected']],
                'corrected' => [['attempt' => 1, 'status' => 'rejected'], ['attempt' => 2, 'status' => 'accepted']],
                default => [['attempt' => 1, 'status' => 'accepted']],
            };
            return Illuminate\Http\Client\Factory::response(['request_id' => $body['request_id'], 'usage' => ['validation_attempts' => $a], 'secret' => 'NEVER_JOURNAL_BODY'], ($body['fixture'] ?? '') === 'outer_failure' ? 503 : 200);
        }
        return Illuminate\Http\Client\Factory::response(['request_id' => $body['request_id'], 'status' => ($body['fixture'] ?? '') === 'reject' ? 'failed' : 'completed', 'reason' => ($body['fixture'] ?? '') === 'reject' ? 'invalid_output' : null, 'secret' => 'NEVER_JOURNAL_BODY'], 200);
    });
    $observer = null;
    if ($enabled) {
        $observer = new EvaluationWorkerObserver(['journal' => $root.'/journal', 'health' => $root.'/health', 'execution_id' => uid(1), 'worker_id' => uid(2), 'workspace_id' => uid(3), 'ai_origin' => 'http://controlled'], function ($job) use ($fault) {
            if ($fault === 'listener') { throw new RuntimeException('NEVER_JOURNAL_EXCEPTION'); }
            return ['run_id' => $job->run, 'workspace_id' => uid(3), 'status' => $job->state];
        });
        $observer->register($events);
        if ($fault === 'disk') { rename($root.'/journal', $root.'/partial-journal'); }
    }
    $post = function (int $rid, string $path, string $fixture = '') use ($http, &$returned) {
        $body = json_encode(['request_id' => uid($rid), 'workspace_id' => uid(3), 'fixture' => $fixture, 'question' => 'NEVER_JOURNAL_QUESTION']);
        $headers = (new App\Services\Retrieval\RetrievalCallSigner)->headers('POST', $path, $body, uid(3), 'retrieval.plan-needs', uid($rid), 1700000000);
        $r = $http->withHeaders($headers)->withBody($body, 'application/json')->post('http://controlled'.$path);
        $returned[] = [$r->status(), $r->body()];
    };
    $planner = '/api/internal/retrieval/plan-needs';
    $generation = '/api/internal/retrieval/generation/current-policy-answer';
    $jobs = [
        new SyntheticObservedJob('11', uid(11), function ($j) use ($post, $planner, $generation) { $post(101, $planner, 'outer_failure'); $post(102, $planner, 'corrected'); $post(103, $generation); $post(104, '/unrelated'); $j->state = 'completed'; }, $events),
        new SyntheticObservedJob('12', uid(12), function ($j) use ($post, $planner, $generation) { $post(201, $planner); $post(202, $generation, 'reject'); $j->state = 'completed'; }, $events),
        new SyntheticObservedJob('13', uid(13), function ($j) { $j->state = 'cancelled'; }, $events),
        new SyntheticObservedJob('14', uid(14), function ($j) { $j->state = 'failed'; throw new RuntimeException('NEVER_JOURNAL_EXCEPTION'); }, $events),
        new SyntheticObservedJob('15', uid(15), function ($j) use ($post, $planner) { $post(301, $planner); $j->state = 'completed'; }, $events),
        new SyntheticObservedJob('16', uid(16), function ($j) use ($post, $planner) { try { $post(401, $planner, 'transport'); } finally { $j->state = 'failed'; } }, $events),
    ];
    $connection = Mockery::mock(Illuminate\Contracts\Queue\Queue::class);
    $connection->shouldReceive('getConnectionName')->andReturn('conversation');
    $unrelated = array_fill(0, 17, 'governance'); $popped = [];
    $connection->shouldReceive('pop')->with('conversation-generation', 0)->andReturnUsing(function () use (&$popped, &$jobs) { $j = array_shift($jobs); $popped[] = $j; return $j; });
    $manager = Mockery::mock(Illuminate\Queue\QueueManager::class);
    $manager->shouldReceive('connection')->with('conversation')->andReturn($connection);
    $handler = Mockery::mock(Illuminate\Contracts\Debug\ExceptionHandler::class);
    $handler->shouldReceive('report')->andReturnUsing(function ($e) use (&$exceptions) { $exceptions[] = get_class($e); });
    $worker = new Illuminate\Queue\Worker($manager, $events, $handler, fn () => false);
    $options = new Illuminate\Queue\WorkerOptions(timeout: 900, sleep: 0, maxTries: 1);
    for ($i = 0; $i < 6; $i++) { $worker->runNextJob('conversation', 'conversation-generation', $options); }
    $events->dispatch(new Illuminate\Queue\Events\WorkerStopping);
    check(count($unrelated) === 17 && count($popped) === 6, 'queue scope');
    $result = ['sent' => $sent, 'returned' => $returned, 'requests' => $requests, 'states' => array_map(fn ($j) => [$j->state, $j->hasFailed()], $popped), 'exceptions' => $exceptions];
    if ($enabled && $fault === '') {
        check(count(glob($root.'/health/*')) === 0, 'healthy observer');
        $rows = array_map(fn ($p) => json_decode(file_get_contents($p), true), glob($root.'/journal/*.json'));
        check(count(array_filter($rows, fn ($r) => $r['kind'] === 'job_start')) === 6, 'job starts');
        check(count(array_filter($rows, fn ($r) => $r['kind'] === 'job_end')) === 6, 'job ends');
        $ends = array_values(array_filter($rows, fn ($r) => $r['kind'] === 'request_end'));
        check(count($ends) === 7 && $ends[1]['attempts'][1]['attempt'] === 2, 'native/outer attempts');
        check($ends[5]['run_id'] === uid(15), 'no context carryover');
        check(end($rows)['kind'] === 'worker_stop', 'shutdown');
        check(! str_contains(json_encode($rows), 'NEVER_JOURNAL'), 'redaction');
        check(! str_contains(json_encode($rows), 'rc1='), 'no signed headers');
    }
    if ($enabled && $fault !== '') { check(is_file($root.'/health/observer-failed'), 'independent health'); }
    Mockery::close();
    return $result;
}

$base = $argv[1];
$off = scenario($base.'/off', false);
foreach (['on' => '', 'listener' => 'listener', 'disk' => 'disk'] as $name => $fault) {
    $on = scenario($base.'/'.$name, true, $fault);
    check($off === $on, 'observational equivalence '.$name);
}
check(EvaluationWorkerObserver::workerArguments() === ['artisan', 'queue:work', 'conversation', '--queue=conversation-generation', '--tries=1', '--timeout=900'], 'exact argv');
foreach (['overlap', 'foreign', 'unknown_terminal', 'no_active_request', 'interrupted'] as $mode) {
    $root = $base.'/'.$mode; mkdir($root); mkdir($root.'/journal'); mkdir($root.'/health');
    $events = new Illuminate\Events\Dispatcher(new Illuminate\Container\Container);
    $observer = new EvaluationWorkerObserver(['journal' => $root.'/journal', 'health' => $root.'/health', 'execution_id' => uid(1), 'worker_id' => uid(2), 'workspace_id' => uid(3), 'ai_origin' => 'http://controlled'],
        fn ($job) => ['run_id' => $job->run, 'workspace_id' => $mode === 'foreign' ? uid(999) : uid(3), 'status' => 'completed']);
    $observer->register($events);
    $job = new SyntheticObservedJob('91', uid(91), fn () => null, $events);
    if ($mode === 'unknown_terminal') {
        $events->dispatch(new Illuminate\Queue\Events\JobProcessed('conversation', $job));
    } elseif ($mode === 'no_active_request') {
        $request = new Illuminate\Http\Client\Request(new GuzzleHttp\Psr7\Request('POST', 'http://controlled/api/internal/retrieval/plan-needs', [], json_encode(['request_id' => uid(901), 'workspace_id' => uid(3)])));
        $events->dispatch(new Illuminate\Http\Client\Events\RequestSending($request));
    } else {
        $events->dispatch(new Illuminate\Queue\Events\JobProcessing('conversation', $job));
        if ($mode === 'overlap') { $events->dispatch(new Illuminate\Queue\Events\JobProcessing('conversation', $job)); }
    }
    $events->dispatch(new Illuminate\Queue\Events\WorkerStopping);
    check(is_file($root.'/health/observer-failed'), 'health for '.$mode);
}
echo json_encode(['actual_worker_jobs_per_scenario' => 6, 'scenarios' => 4, 'request_response_exception_job_equivalence' => true, 'unrelated_synthetic_governance_jobs_preserved_per_scenario' => 17, 'real_evaluation_jobs_touched' => 0, 'provider_requests' => 0])."\n";
