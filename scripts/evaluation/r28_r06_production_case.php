<?php

declare(strict_types=1);

// This adapter invokes the existing production action once. The host-side
// governed runner verifies and consumes one-use authority before invoking it.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (($argv[1] ?? null) === '--preflight') {
    $workspaces = [];
    foreach ([
        ['5323cd6b-8b7a-40f9-98b0-9413c75f643e', 'ae454ce6-4d91-4c7e-9a40-b4750b696a6b'],
        ['be22830b-ddf1-4de5-b852-f7f4c111cf8e', null],
        ['4db6ffe2-6974-415d-8fd3-002b8fb05d05', '6972994f-c594-42ef-a747-39d01ae24fc3'],
    ] as [$workspaceId, $userId]) {
        $workspace = App\Models\Workspace::query()->where('public_id', $workspaceId)->sole();
        if ($userId !== null) {
            $user = App\Models\User::query()->where('public_id', $userId)->sole();
            app(App\Queries\Workspaces\FindWorkspaceForUser::class)->handle($user, $workspaceId);
        }
        $generation = $workspace->activeCorpusGeneration()->with(['embeddingSpaceGeneration.embeddingProfile', 'sparseSpaceGeneration.sparseEmbeddingProfile'])->sole();
        $dense = $generation->embeddingSpaceGeneration;
        $sparse = $generation->sparseSpaceGeneration;
        $workspaces[] = [
            'workspace_id' => $workspaceId,
            'user_public_id' => $userId,
            'generation_public_id' => $generation->public_id,
            'generation_status' => $generation->status->value,
            'expected_point_count' => $generation->expected_point_count,
            'assigned_point_count' => Illuminate\Support\Facades\DB::table('workspace_corpus_generation_chunks')->where('workspace_corpus_generation_id', $generation->id)->count(),
            'foreign_chunk_assignments' => Illuminate\Support\Facades\DB::table('workspace_corpus_generation_chunks as a')
                ->join('document_chunks as c', 'c.id', '=', 'a.document_chunk_id')
                ->where('a.workspace_corpus_generation_id', $generation->id)
                ->where('c.workspace_id', '<>', $workspace->id)->count(),
            'point_manifest_digest' => $generation->point_manifest_digest,
            'dense_provider' => $dense->embeddingProfile->provider,
            'dense_model' => $dense->embeddingProfile->model,
            'dense_dimensions' => $dense->embeddingProfile->dimensions,
            'sparse_provider' => $sparse?->sparseEmbeddingProfile?->provider,
            'sparse_model' => $sparse?->sparseEmbeddingProfile?->model,
        ];
    }
    echo json_encode([
        'environment' => app()->environment(),
        'workspaces' => $workspaces,
        'generation_model' => config('generation.model'),
        'planner' => config('retrieval.planner'),
        'retrieval_limits' => collect(config('retrieval'))->only(['timeout_seconds', 'max_attempts', 'candidate_k', 'candidate_k_max', 'max_eligible_documents', 'timeout_budget'])->all(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit;
}

$payload = json_decode((string) file_get_contents('php://stdin'), true, flags: JSON_THROW_ON_ERROR);
$required = ['case_id', 'variant_id', 'utterance', 'scope', 'workspace_id', 'user_public_id'];
if (! is_array($payload) || array_keys($payload) !== $required) {
    throw new RuntimeException('Question-only case dispatch shape is invalid.');
}
foreach ($required as $field) {
    if (! is_string($payload[$field]) || trim($payload[$field]) === '') {
        throw new RuntimeException('Question-only case dispatch field is invalid.');
    }
}
if (! in_array($payload['scope'], ['primary', 'foreign_tenant', 'security_test'], true)) {
    throw new RuntimeException('Frozen case scope is invalid.');
}
if (config('conversation.needs_workspace_ids') !== [$payload['workspace_id']]) {
    throw new RuntimeException('The target workspace is not the sole needs opt-in.');
}
$classification = $payload['scope'] === 'security_test' ? 'injection' : 'primary';
$user = App\Models\User::query()->where('public_id', $payload['user_public_id'])->sole();
$shadowReviews = [];
if (getenv('RETRIEVAL_LOCATION_REFERENCE_SHADOW') === 'true') {
    if (config('retrieval.location_reference_shadow.enabled') !== true) {
        throw new RuntimeException('Location shadow trial flag was not enabled in Laravel config.');
    }
    Illuminate\Support\Facades\Event::listen(Illuminate\Log\Events\MessageLogged::class,
        function (Illuminate\Log\Events\MessageLogged $event) use (&$shadowReviews): void {
            if ($event->message === 'retrieval.location_reference.shadow.v1') {
                $shadowReviews[] = $event->context;
            }
        });
}
$started = now()->toIso8601String();
$observation = app(App\Actions\Evaluation\RunR28ProductionPathDiagnostic::class)->handleCase(
    $user,
    $payload['workspace_id'],
    [
        'case_id' => $payload['case_id'],
        'variant_id' => $payload['variant_id'],
        'utterance' => $payload['utterance'],
    ],
    $classification,
);
$run = App\Models\GenerationRun::query()->where('public_id', $observation['run_id'])
    ->with('assistantMessage')->sole();
$plan = $observation['production_trace']['needs_plan'] ?? null;
$clarificationSource = $observation['retrieval']['clarification_source'] ?? null;
$plannerClarification = ($clarificationSource instanceof App\Enums\RetrievalClarificationSource
    ? $clarificationSource->value : $clarificationSource) === 'planner'
    && ($observation['usage']['planner_failure'] ?? null) !== null;
$record = [
    'case_id' => $payload['case_id'],
    'variant_id' => $payload['variant_id'],
    'utterance' => $payload['utterance'],
    'frozen_scope' => $payload['scope'],
    'needs_opt_in' => config('conversation.needs_workspace_ids'),
    'needs_pipeline_exercised' => (is_array($plan) && array_key_exists('needs', $plan)) || $plannerClarification,
    'started_at' => $started,
    'finished_at' => now()->toIso8601String(),
    'observation' => $observation,
    'displayed_text' => $run->assistantMessage?->display_text,
];
if (getenv('RETRIEVAL_LOCATION_REFERENCE_SHADOW') === 'true') {
    $record['shadow_reviews'] = $shadowReviews;
}
echo json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
