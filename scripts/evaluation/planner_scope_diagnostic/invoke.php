<?php

use App\Exceptions\RetrievalPlannerException;
use App\Models\User;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use App\Services\Generation\GenerationProfileFactory;
use App\Services\Retrieval\RetrievalClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

// Standalone evaluation entry; no conversation, queue, retrieval or generation action.
function plannerScopeDiagnostic(array $input): array
{
    $requests = [];
    $workspace = '5323cd6b-8b7a-40f9-98b0-9413c75f643e';
    $actor = 'ae454ce6-4d91-4c7e-9a40-b4750b696a6b';
    if (! Str::isUuid($input['invocation_id'] ?? '')
        || ! is_string($input['utterance'] ?? null) || $input['utterance'] === '') {
        throw new InvalidArgumentException('Invalid diagnostic input.');
    }
    $at = CarbonImmutable::now('Europe/London');
    $user = User::where('public_id', $actor)->firstOrFail();
    $scope = app(BuildAuthorisedKnowledgeScope::class)->handle($user, $workspace);
    $pin = app(GenerationProfileFactory::class)->needsPin($workspace);
    if ($pin !== $input['expected_profile'] || ($pin['product_profile'] ?? null) !== 'current-policy-pool-v1') {
        throw new RuntimeException('Diagnostic profile drift.');
    }
    Event::listen(RequestSending::class, function ($event) use (&$requests, $workspace): void {
        $request = $event->request;
        if (parse_url($request->url(), PHP_URL_PATH) !== '/api/internal/retrieval/plan-needs') {
            throw new RuntimeException('Unexpected diagnostic downstream endpoint.');
        }
        $body = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);
        if ($body['workspace_id'] !== $workspace || $body['contract_version'] !== 3
            || $body['product_profile'] !== 'current-policy-pool-v1' || count($body['user_turns']) !== 1) {
            throw new RuntimeException('Diagnostic request identity drift.');
        }
        $requests[] = ['request_id' => $body['request_id'], 'evaluated_at' => $body['evaluated_at'],
            'body_sha256' => hash('sha256', $request->body()), 'user_turns' => $body['user_turns'],
            'catalogue_sha256' => hash('sha256', json_encode($body['catalogue'], JSON_THROW_ON_ERROR)),
            'signed' => $request->hasHeader('X-Retrieval-Caller-Signature'),
            'purpose' => $request->header('X-Retrieval-Caller-Purpose')[0] ?? null];
    });
    $record = ['invocation_id' => $input['invocation_id'], 'evaluated_at' => $at->toIso8601String(),
        'timezone' => 'Europe/London', 'profile' => $pin, 'workspace' => $workspace, 'actor' => $actor];
    try {
        $plan = app(RetrievalClient::class)->planNeeds($scope->workspace,
            [['turn_id' => 'user-1', 'text' => $input['utterance']]], $at, $pin);
        $record += ['status' => 'accepted', 'plan' => $plan->value, 'lineage' => $plan->lineage->toArray(), 'usage' => $plan->usage->toArray()];
    } catch (RetrievalPlannerException $error) {
        $record += ['status' => 'planner_rejected', 'category' => $error->category,
            'systemic' => $error->systemic, 'provider_status' => $error->providerStatus,
            'attempt_count' => $error->attemptCount, 'validation' => $error->validation,
            'completion' => $error->completion, 'validation_attempts' => $error->validationAttempts];
    }
    $record['requests'] = $requests;

    return $record;
}

if (! defined('PLANNER_DIAGNOSTIC_LIBRARY_ONLY')) {
    require '/app/vendor/autoload.php';
    $app = require '/app/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    try {
        $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        echo json_encode(plannerScopeDiagnostic($input), JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        // Unknown errors halt; never serialize exception prose or credentials.
        echo json_encode(['status' => 'halt', 'exception_class' => get_class($error)], JSON_THROW_ON_ERROR);
        exit(2);
    }
}
