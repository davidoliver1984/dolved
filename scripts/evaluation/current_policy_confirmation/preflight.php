<?php

$root = getenv('CURRENT_POLICY_APP_ROOT') ?: '/workspace/apps/api';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$user = App\Models\User::where('public_id', $input['actor'])->firstOrFail();
$scope = app(App\Queries\Retrieval\BuildAuthorisedKnowledgeScope::class)->handle($user, $input['workspace']);
$pin = app(App\Services\Generation\GenerationProfileFactory::class)->needsPin($scope->workspace->public_id);
if (($pin['product_profile'] ?? null) !== 'current-policy-pool-v1') { throw new RuntimeException('Current-policy profile not enabled in release context.'); }
$membership=App\Models\WorkspaceMembership::where('workspace_id',$scope->workspace->id)->where('user_id',$user->id)->firstOrFail();
$policy=app(App\Services\Retrieval\ResolveEvidenceThresholdPolicy::class)->handle($scope->activeCorpusGeneration);
echo json_encode(['workspace'=>$scope->workspace->public_id,'actor'=>$user->public_id,
    'membership'=>$membership->public_id,'membership_role'=>$membership->role->value,
    'threshold_policy'=>$policy->public_id,'threshold_policy_fingerprint'=>$policy->fingerprint,
    'threshold_policy_record_sha256'=>hash('sha256',json_encode($policy->getAttributes(),JSON_THROW_ON_ERROR)),
    'enabled_workspaces'=>config('conversation.current_policy_workspace_ids'),
    'conversation_queue'=>config('queue.connections.conversation'),
    'corpus_generation'=>$scope->activeCorpusGeneration?->public_id,'profile'=>$pin,
    'database_driver'=>config('database.default'), 'run_timeout_seconds'=>(int) config('conversation.run_timeout_seconds')], JSON_THROW_ON_ERROR);
