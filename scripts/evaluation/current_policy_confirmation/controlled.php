<?php

// Development-only fixture server. Never loaded by a service provider or product route.
require '/workspace/apps/api/vendor/autoload.php';
$app = require '/workspace/apps/api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.connections.pgsql.host') !== 'test-postgres') {
    throw new RuntimeException('Disposable test database required.');
}
config(['queue.connections.conversation.driver' => 'sync']);
// Match the existing product Compose contract used by Next's cookie allowlist.
config(['session.cookie' => 'rag-platform-session']);
// The HTTP fixture uses the real clock: frozen past time would expire browser
// session cookies before the test starts. Source fixtures remain current.
$file = '/tmp/stop3-fixtures.json';
if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'seed') {
    $fixtures = [];
    foreach (['completed', 'location', 'capacity', 'invalid_output', 'partial_timeout', 'unavailable', 'all_history', 'all_unclear', 'scope_fact'] as $mode) {
        Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory);
        $test = new Tests\Feature\CurrentPolicyBackendTest('test_default_off_refuses_backend_execution_before_any_call');
        $method = new ReflectionMethod($test, 'fixture');
        [$message, $pin] = $method->invoke($test, $mode);
        $saved = app(App\Actions\Generation\AnswerCurrentPolicyMessage::class)->handle($message, $pin, Carbon\CarbonImmutable::now());
        $calls = Illuminate\Support\Facades\Http::recorded()->map(fn ($pair) => [
            'path' => parse_url($pair[0]->url(), PHP_URL_PATH), 'status' => $pair[1]->status(), 'body' => $pair[1]->json(),
        ])->all();
        $user = $message->createdBy;
        $user->forceFill(['password' => Illuminate\Support\Facades\Hash::make('disposable-test-only')])->save();
        $fixtures[] = ['id' => $mode, 'workspace' => $message->conversation->workspace->public_id,
            'user_id' => $user->id, 'email' => $user->email, 'utterance' => $message->display_text,
            'saved_text' => $saved->display_text, 'calls' => $calls];
    }
    file_put_contents($file, json_encode($fixtures, JSON_THROW_ON_ERROR));
    echo "Synthetic fixtures seeded; no external services called.\n";
    exit;
}
$fixtures = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'describe') {
    echo json_encode(array_map(function ($f) {
        unset($f['calls']);
        $f['actor'] = App\Models\User::findOrFail($f['user_id'])->public_id;
        return $f;
    }, $fixtures), JSON_THROW_ON_ERROR);
    exit;
}
config(['conversation.current_policy_workspace_ids' => array_column($fixtures, 'workspace')]);
$request = Illuminate\Http\Request::capture();
$fixture = collect($fixtures)->first(fn ($f) => PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'execute'
    ? $f['workspace'] === ($argv[3] ?? null) : str_contains($request->path(), $f['workspace']));
// Standalone disposable fixture only: one-shot job delivery, not a running worker.
$async = ($fixture['async'] ?? false) === true;
if ($async) { Illuminate\Support\Facades\Queue::fake(); }
Illuminate\Support\Facades\Http::preventStrayRequests();
$indices = [];
Illuminate\Support\Facades\Http::fake(function ($r) use ($fixture, &$indices) {
    $path = parse_url($r->url(), PHP_URL_PATH);
    $matches = array_values(array_filter($fixture['calls'] ?? [], fn ($c) => $c['path'] === $path));
    $index = $indices[$path] ?? 0;
    $indices[$path] = $index + 1;
    if (! isset($matches[$index])) {
        throw new RuntimeException('Unexpected controlled provider request.');
    }
    file_put_contents('/tmp/stop3-provider-boundary.ndjson', json_encode(['workspace' => $fixture['workspace'], 'path' => $path,
        'run_id' => PHP_SAPI === 'cli' && ($GLOBALS['argv'][1] ?? '') === 'execute' ? (int) $GLOBALS['argv'][2] : null])."\n", FILE_APPEND | LOCK_EX);
    if (str_ends_with($path, '/current-policy-answer')) { usleep((int) ($fixture['delay_ms'] ?? 0) * 1000); }
    $body = $matches[$index]['body'];
    if (isset($body['request_id'])) {
        $body['request_id'] = $r->data()['request_id'];
    }
    return Illuminate\Support\Facades\Http::response($body, $matches[$index]['status']);
});
if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'execute') {
    app(App\Actions\Conversation\OrchestrateConversationRun::class)->handle((int) $argv[2]);
    exit;
}
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
if ($async && $request->isMethod('post') && str_ends_with($request->path(), '/messages') && $response->getStatusCode() === 202) {
    $publicId = json_decode($response->getContent(), true)['data']['run_id'];
    $run = App\Models\GenerationRun::where('public_id', $publicId)->firstOrFail();
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' execute '.(int) $run->id.' '.escapeshellarg($fixture['workspace']).' > /tmp/stop3a-job-'.(int) $run->id.'.log 2>&1 &';
    exec($command);
}
$response->send();
$kernel->terminate($request, $response);
