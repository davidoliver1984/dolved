<?php

require '/workspace/apps/api/vendor/autoload.php';
$app = require '/workspace/apps/api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'test-postgres') {
    throw new RuntimeException('Disposable PostgreSQL required.');
}
Carbon\CarbonImmutable::setTestNow('2026-11-16T12:00:00Z');
Illuminate\Support\Facades\Queue::fake();
$fixture = json_decode(file_get_contents('/tmp/stop3-fixtures.json'), true, flags: JSON_THROW_ON_ERROR)[0];
config(['conversation.current_policy_workspace_ids' => [$fixture['workspace']]]);
$wait = function (string $file): void {
    $deadline = microtime(true) + 15;
    while (! file_exists($file)) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Race barrier expired.'); }
        usleep(10000);
    }
};
if (($argv[1] ?? '') === 'worker') {
    $dir = $argv[2]; $operation = $argv[3];
    $state = json_decode(file_get_contents($dir.'/state.json'), true, flags: JSON_THROW_ON_ERROR);
    $indices = [];
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake(function ($r) use ($fixture, &$indices, $dir, $state, $wait) {
        $path = parse_url($r->url(), PHP_URL_PATH);
        $matches = array_values(array_filter($fixture['calls'], fn ($c) => $c['path'] === $path));
        $i = $indices[$path] ?? 0; $indices[$path] = $i + 1;
        if (!isset($matches[$i])) { throw new RuntimeException('Unexpected provider seam.'); }
        file_put_contents($dir.'/calls.ndjson', json_encode(['pid' => getmypid(), 'path' => $path])."\n", FILE_APPEND | LOCK_EX);
        if (str_ends_with($path, '/current-policy-answer')) {
            touch($dir.'/generation');
            if ($state['case'] === 'cancel') { $wait($dir.'/cancelled'); } else { usleep(300000); }
        }
        $body = $matches[$i]['body'];
        if (isset($body['request_id'])) { $body['request_id'] = $r->data()['request_id']; }
        return Illuminate\Support\Facades\Http::response($body, $matches[$i]['status']);
    });
    $wait($dir.'/start');
    if ($operation === 'submit') {
        app(App\Actions\Conversation\SubmitConversationMessage::class)->handle(
            App\Models\Conversation::findOrFail($state['conversation']), App\Models\User::findOrFail($fixture['user_id']), $fixture['utterance'], $state['key']);
    } elseif ($operation === 'cancel') {
        $wait($dir.'/generation');
        app(App\Actions\Conversation\CancelGenerationRun::class)->handle(App\Models\GenerationRun::findOrFail($state['run']));
        touch($dir.'/cancelled');
    } else {
        if ($operation === 'late_duplicate') { $wait($dir.'/persisting'); }
        if ($state['case'] === 'completion') {
            Illuminate\Support\Facades\Event::listen('eloquent.created: '.App\Models\Message::class, function ($message) use ($dir) {
                if ($message->role === App\Enums\MessageRole::Assistant) { touch($dir.'/persisting'); usleep(800000); }
            });
        }
        app(App\Actions\Conversation\OrchestrateConversationRun::class)->handle($state['run']);
    }
    echo json_encode(['pid' => getmypid(), 'operation' => $operation, 'finished' => true]);
    exit;
}
$report = [];
foreach (['claim', 'submit', 'cancel', 'completion'] as $case) {
    $dir = '/tmp/stop3-race-'.Illuminate\Support\Str::uuid(); mkdir($dir);
    $user = App\Models\User::findOrFail($fixture['user_id']);
    $workspace = App\Models\Workspace::where('public_id', $fixture['workspace'])->firstOrFail();
    $conversation = app(App\Actions\Conversation\CreateConversation::class)->handle($workspace, $user);
    $state = ['case' => $case, 'conversation' => $conversation->id, 'key' => (string) Illuminate\Support\Str::uuid()];
    if ($case !== 'submit') {
        $state['run'] = app(App\Actions\Conversation\SubmitConversationMessage::class)->handle($conversation, $user, $fixture['utterance'], $state['key'])->id;
    }
    file_put_contents($dir.'/state.json', json_encode($state));
    $operations = match ($case) { 'submit' => ['submit', 'submit'], 'cancel' => ['execute', 'cancel'], 'completion' => ['execute', 'late_duplicate'], default => ['execute', 'execute'] };
    $processes = [];
    foreach ($operations as $i => $operation) {
        $processes[] = proc_open([PHP_BINARY, __FILE__, 'worker', $dir, $operation], [0=>['file','/dev/null','r'],1=>['file', $dir."/worker-$i.json",'w'],2=>['file',$dir."/worker-$i.stderr",'w']], $pipes);
    }
    touch($dir.'/start');
    foreach ($processes as $process) { if (proc_close($process) !== 0) { throw new RuntimeException('Race worker failed: '.$dir); } }
    $run = $conversation->generationRuns()->firstOrFail();
    if ($case === 'submit') {
        $state['run'] = $run->id;
        file_put_contents($dir.'/state.json', json_encode($state));
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $dir, 'execute'], [0=>['file','/dev/null','r'],1=>['file',$dir.'/delivery.json','w'],2=>['file',$dir.'/delivery.stderr','w']], $pipes);
        if (proc_close($process) !== 0) { throw new RuntimeException('Post-race delivery failed: '.$dir); }
        $run->refresh();
    }
    $calls = file_exists($dir.'/calls.ndjson') ? array_map(fn ($s) => json_decode($s,true), file($dir.'/calls.ndjson', FILE_IGNORE_NEW_LINES)) : [];
    $generation = count(array_filter($calls,fn ($c)=>str_ends_with($c['path'],'/current-policy-answer')));
    $answers = $conversation->messages()->where('role','assistant')->count();
    $completed = $run->deliveryEvents()->where('event_type','answer_completed')->count();
    $expected = $case === 'cancel' ? 0 : 1;
    if ($answers !== $expected || $completed !== $expected || $generation > 1 || $conversation->generationRuns()->count() !== 1 || $conversation->messages()->where('role','user')->count() !== 1) {
        throw new RuntimeException('Race invariant failed: '.$dir);
    }
    $report[] = ['case'=>$case,'directory'=>$dir,'workers'=>array_map(fn ($i)=>json_decode(file_get_contents($dir."/worker-$i.json"),true),[0,1]),
        'run_status'=>$run->status->value,'answers'=>$answers,'completion_events'=>$completed,'generation_requests'=>$generation,'calls'=>$calls,
        'note'=>$case==='submit'?'Two simultaneous submissions, then controlled delivery of their single run.':($case==='cancel'?'Cancellation wins during controlled generation: zero saved answers is required, not a duplicate completion.':'One saved answer and completion; second delivery cannot generate.')];
}
file_put_contents('/tmp/stop3-races.json', json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "Four separate-process PostgreSQL races completed.\n";
