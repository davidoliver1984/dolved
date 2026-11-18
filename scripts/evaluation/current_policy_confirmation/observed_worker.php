<?php

declare(strict_types=1);

// Future release only: read-only mount this directory; separate bound writable
// evidence journal + health paths. Never registered in the serving application.
require '/app/vendor/autoload.php';
$config = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
if (($config['workspace_id'] ?? null) !== '5323cd6b-8b7a-40f9-98b0-9413c75f643e' || count($argv) !== 2) {
    throw new RuntimeException('observer evaluation boundary');
}
$rehearsal = defined('EVALUATION_EMPTY_QUEUE_REHEARSAL') && EVALUATION_EMPTY_QUEUE_REHEARSAL === true;
if ($rehearsal !== (($config['mode'] ?? null) === 'empty_queue_rehearsal')) {
    throw new RuntimeException('rehearsal_mode_boundary');
}
$sources = ['observed_worker.php', 'worker_observer.php'];
if ($rehearsal) { $sources = [...$sources, 'rehearsal_worker.php', 'rehearsal_isolation.php']; }
if (array_keys($config['source_files'] ?? []) !== $sources) {
    throw new RuntimeException('observer source binding');
}
foreach ($config['source_files'] as $name => $digest) {
    if (hash_file('sha256', __DIR__.'/'.$name) !== $digest) { throw new RuntimeException('observer mounted source drift'); }
}
require __DIR__.'/worker_observer.php';
$app = require '/app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$observer = new EvaluationWorkerObserver($config, static function ($job) use ($config): array {
    $payload = $job->payload();
    if (($payload['data']['commandName'] ?? null) !== App\Jobs\ExecuteGenerationRun::class) {
        throw new RuntimeException('non-conversation job');
    }
    $command = @unserialize($payload['data']['command'], ['allowed_classes' => [App\Jobs\ExecuteGenerationRun::class]]);
    if (! $command instanceof App\Jobs\ExecuteGenerationRun) { throw new RuntimeException('job class'); }
    $workspace = App\Models\Workspace::query()->where('public_id', $config['workspace_id'])->firstOrFail(['id', 'public_id']);
    $run = App\Models\GenerationRun::query()->where('workspace_id', $workspace->id)->findOrFail($command->generationRunId);
    return ['run_id' => $run->public_id, 'workspace_id' => $workspace->public_id, 'status' => $run->status->value];
});
$observer->register($app->make(Illuminate\Contracts\Events\Dispatcher::class));
if ($rehearsal) {
    require __DIR__.'/rehearsal_isolation.php';
    if (($config['rehearsal_lifecycle'] ?? null) !== '/rehearsal-lifecycle') { throw new RuntimeException('rehearsal_lifecycle_boundary'); }
    installEmptyQueueRehearsal($app->make(Illuminate\Contracts\Events\Dispatcher::class), $app->make(Illuminate\Http\Client\Factory::class), $config['rehearsal_lifecycle']);
}
register_shutdown_function(fn () => $observer->stop());
$input = new Symfony\Component\Console\Input\ArgvInput(EvaluationWorkerObserver::workerArguments());
$status = $kernel->handle($input, new Symfony\Component\Console\Output\ConsoleOutput);
$kernel->terminate($input, $status);
$observer->stop();
exit($status);
