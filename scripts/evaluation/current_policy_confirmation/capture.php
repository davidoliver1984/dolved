<?php

// Read-only product receipt export. Called only with release-bound actor/workspace/run IDs.
$root = getenv('CURRENT_POLICY_APP_ROOT') ?: '/workspace/apps/api';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$user = App\Models\User::where('public_id', $input['actor'])->firstOrFail();
$scope = app(App\Queries\Retrieval\BuildAuthorisedKnowledgeScope::class)->handle($user, $input['workspace']);
$run = App\Models\GenerationRun::where('workspace_id', $scope->workspace->id)->where('public_id', $input['run'])->firstOrFail();
if ($run->userMessage->created_by_user_id !== $user->id) { throw new RuntimeException('Run actor mismatch.'); }
$answer = $run->generatedAnswer;
echo json_encode(['run_id'=>$run->public_id,'status'=>$run->status->value,'failure_code'=>$run->failure_code?->value,
    'needs_profile'=>$run->needs_profile,'usage'=>$run->usage,'text'=>$run->assistantMessage?->display_text,
    'answer'=>$answer?->toArray(), 'evidence'=>$answer?->evidenceSnapshots->toArray(),
    'events'=>$run->deliveryEvents->map(fn ($e)=>['sequence'=>$e->sequence,'type'=>$e->event_type->value,'payload'=>$e->safe_payload])->all()], JSON_THROW_ON_ERROR);
