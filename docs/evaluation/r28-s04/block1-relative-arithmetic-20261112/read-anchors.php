<?php
// Read-only retained-run snapshot inspection; no orchestration or providers.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ids = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$rows = Illuminate\Support\Facades\DB::table('retrieval_outcome_snapshots as s')
    ->join('generation_runs as r', 'r.id', '=', 's.generation_run_id')
    ->whereIn('r.public_id', $ids)
    ->select(['r.public_id as run_id', 's.workspace_id', 's.evaluated_at'])
    ->orderBy('s.evaluated_at')->get();
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
