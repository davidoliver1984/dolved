<?php

declare(strict_types=1);

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$workspaceId = '5323cd6b-8b7a-40f9-98b0-9413c75f643e';
if (config('conversation.needs_workspace_ids') !== [$workspaceId]) {
    throw new RuntimeException('Primary-only needs opt-in is required.');
}
$inputPath = '/tmp/dolved-r08-r17-planner-variance-inputs-20261107.json';
if (hash_file('sha256', $inputPath) !== 'cf94f3fa1513ecb61ac672ca3450cdfb5b29ed74c055f09e67c2cd83472a2bad') {
    throw new RuntimeException('Diagnostic input digest mismatch.');
}
$input = json_decode((string) file_get_contents($inputPath), true, flags: JSON_THROW_ON_ERROR);
$calls = $input['calls'] ?? null;
$expectedIds = ['r08-01', 'r08-02', 'r08-03', 'r08-04', 'r08-05',
    'r17-01', 'r17-02', 'r17-03', 'r17-04', 'r17-05'];
if (! is_array($calls) || array_column($calls, 'id') !== $expectedIds) {
    throw new RuntimeException('Expected exactly ten ordered planner calls.');
}

$user = App\Models\User::query()->where('public_id', 'ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
$workspace = app(App\Queries\Workspaces\FindWorkspaceForUser::class)
    ->handle($user, $workspaceId)->workspace;
$planner = app(App\Services\Retrieval\RetrievalClient::class);
$evaluatedAt = Carbon\CarbonImmutable::now('UTC');
$directory = '/tmp/dolved-r08-r17-planner-variance-run-20261107';
if (file_exists($directory) || ! mkdir($directory, 0700) || ! mkdir($directory.'/calls', 0700)) {
    throw new RuntimeException('Refusing to overwrite diagnostic evidence.');
}
$dispatch = fopen($directory.'/dispatch-records.ndjson', 'x');
if ($dispatch === false) {
    throw new RuntimeException('Cannot create diagnostic dispatch record.');
}
$started = microtime(true);
foreach ($calls as $call) {
    if (array_keys($call) !== ['id', 'contrast', 'text']
        || ! is_string($call['text']) || trim($call['text']) === '') {
        throw new RuntimeException('Invalid call shape.');
    }
    if (microtime(true) - $started >= 90 * 60) {
        throw new RuntimeException('Wall-clock frame reached before next planner call.');
    }
    $record = [
        'id' => $call['id'],
        'contrast' => $call['contrast'],
        'question' => $call['text'],
        'workspace_id' => $workspaceId,
        'needs_opt_in' => config('conversation.needs_workspace_ids'),
        'evaluated_at' => $evaluatedAt->toIso8601String(),
        'started_at' => now()->toIso8601String(),
    ];
    fwrite($dispatch, json_encode(['id' => $call['id'], 'event' => 'dispatched', 'at' => $record['started_at']],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    fflush($dispatch);
    try {
        $plan = $planner->planNeeds($workspace, [['turn_id' => 'user-1', 'text' => $call['text']]], $evaluatedAt);
        $record['plan'] = $plan->value;
        $record['lineage'] = $plan->lineage->toArray();
        $record['usage'] = $plan->usage->toArray();
        $record['needs_pipeline_exercised'] = is_array($plan->value['needs'] ?? null);
    } catch (Throwable $exception) {
        $record['failure'] = ['class' => get_class($exception), 'code' => (string) $exception->getCode()];
        $record['needs_pipeline_exercised'] = false;
    }
    $record['finished_at'] = now()->toIso8601String();
    $out = fopen($directory.'/calls/'.$call['id'].'.json', 'x');
    if ($out === false) {
        throw new RuntimeException('Refusing to overwrite a call record.');
    }
    fwrite($out, json_encode($record, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
    fclose($out);
    fwrite($dispatch, json_encode(['id' => $call['id'], 'event' => 'recorded', 'at' => $record['finished_at'],
        'needs_pipeline_exercised' => $record['needs_pipeline_exercised']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    fflush($dispatch);
    $binding = collect($record['plan']['bindings'] ?? [])->firstWhere('need_id', 'n1');
    $need = collect($record['plan']['needs'] ?? [])->firstWhere('need_id', 'n1');
    echo json_encode(['id' => $call['id'], 'needs_pipeline_exercised' => $record['needs_pipeline_exercised'],
        'subject_status' => $binding['subject_status'] ?? null, 'family_ids' => $binding['family_ids'] ?? null,
        'operation' => $need['operation'] ?? null, 'applicability' => $need['applicability'] ?? null,
        'failure' => $record['failure'] ?? null], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    flush();
}
fclose($dispatch);
