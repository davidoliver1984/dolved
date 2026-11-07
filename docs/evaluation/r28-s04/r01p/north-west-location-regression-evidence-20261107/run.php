<?php

declare(strict_types=1);

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$workspaceId = '5323cd6b-8b7a-40f9-98b0-9413c75f643e';
if (config('conversation.needs_workspace_ids') !== [$workspaceId]) {
    throw new RuntimeException('Primary-only needs opt-in is required.');
}
$questionsPath = '/tmp/dolved-north-west-location-regression-questions-20261107.json';
if (hash_file('sha256', $questionsPath) !== '993a505b12f1cf6c67f2d1f8ddd351861208050e0a5a179b609e4a44a379140a') {
    throw new RuntimeException('Question file digest mismatch.');
}
$input = json_decode((string) file_get_contents($questionsPath), true, flags: JSON_THROW_ON_ERROR);
$questions = $input['questions'] ?? null;
$expectedIds = ['r08', 'r16', 'r17', 'r19', 'm01', 'm05', 'm06', 'm09', 'm10', 'm11',
    'l14', 'l19', 'l23', 'l25', 'l26', 'n17', 'n21'];
if (! is_array($questions) || array_column($questions, 'id') !== $expectedIds) {
    throw new RuntimeException('Question IDs or order mismatch.');
}
foreach ($questions as $question) {
    if (array_keys($question) !== ['id', 'text'] || ! is_string($question['text']) || trim($question['text']) === '') {
        throw new RuntimeException('Invalid question shape.');
    }
}

$user = App\Models\User::query()->where('public_id', 'ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
$workspace = App\Models\Workspace::query()->where('public_id', $workspaceId)->sole();
$region = App\Models\OrganisationalLocation::query()->where('workspace_id', $workspace->id)
    ->where('public_id', 'ac53a327-426b-41bf-b4ba-4dc61d7e5d64')->sole();
$alias = App\Models\OrganisationalLocationAlias::query()->where('workspace_id', $workspace->id)
    ->where('normalised_alias', 'north west')->sole();
if ($region->name !== 'North West Region' || $alias->organisational_location_id !== $region->id) {
    throw new RuntimeException('North West alias is not bound to the authorised region.');
}
$directory = '/tmp/dolved-north-west-location-regression-run-20261107';
if (file_exists($directory) || ! mkdir($directory, 0700) || ! mkdir($directory.'/cases', 0700)) {
    throw new RuntimeException('Refusing to overwrite run evidence.');
}
$dispatch = fopen($directory.'/dispatch-records.ndjson', 'x');
if ($dispatch === false) {
    throw new RuntimeException('Could not create dispatch record.');
}
$runner = app(App\Actions\Evaluation\RunR28ProductionPathDiagnostic::class);
$started = microtime(true);
foreach ($questions as $question) {
    if (microtime(true) - $started >= 90 * 60) {
        throw new RuntimeException('Wall-clock frame reached before next dispatch.');
    }
    $id = $question['id'];
    $record = ['case_id' => $id, 'utterance' => $question['text'],
        'needs_opt_in' => config('conversation.needs_workspace_ids'), 'started_at' => now()->toIso8601String()];
    fwrite($dispatch, json_encode(['case_id' => $id, 'event' => 'dispatched', 'at' => $record['started_at']],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    fflush($dispatch);
    try {
        $record['observation'] = $runner->handleCase($user, $workspaceId, [
            'case_id' => $id, 'variant_id' => 'north-west-location-regression-once-20261107',
            'utterance' => $question['text']], 'primary');
        $generationRun = App\Models\GenerationRun::query()->where('public_id', $record['observation']['run_id'])
            ->with('assistantMessage')->sole();
        $record['displayed_text'] = $generationRun->assistantMessage?->display_text;
    } catch (Throwable $exception) {
        $record['execution_exception'] = ['class' => get_class($exception), 'code' => (string) $exception->getCode()];
    }
    $record['finished_at'] = now()->toIso8601String();
    $plan = $record['observation']['production_trace']['needs_plan'] ?? null;
    $record['needs_pipeline_exercised'] = is_array($plan) && array_key_exists('needs', $plan);
    $caseFile = fopen($directory.'/cases/'.$id.'.json', 'x');
    if ($caseFile === false) {
        throw new RuntimeException('Refusing to overwrite case evidence.');
    }
    fwrite($caseFile, json_encode($record, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
    fclose($caseFile);
    fwrite($dispatch, json_encode(['case_id' => $id, 'event' => 'recorded', 'at' => $record['finished_at'],
        'needs_pipeline_exercised' => $record['needs_pipeline_exercised']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    fflush($dispatch);
    echo json_encode(['case_id' => $id, 'needs_pipeline_exercised' => $record['needs_pipeline_exercised'],
        'retrieval_outcome' => $record['observation']['retrieval']['outcome'] ?? null,
        'generation_outcome' => $record['observation']['generation']['outcome'] ?? null,
        'scope_fact' => $record['observation']['production_trace']['need.n1.scope_fact'] ?? null,
        'exception' => $record['execution_exception'] ?? null], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    flush();
}
fclose($dispatch);
