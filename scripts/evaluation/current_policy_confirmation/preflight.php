<?php

require '/workspace/apps/api/vendor/autoload.php';
$app = require '/workspace/apps/api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$user = App\Models\User::where('public_id', $input['actor'])->firstOrFail();
$scope = app(App\Queries\Retrieval\BuildAuthorisedKnowledgeScope::class)->handle($user, $input['workspace']);
$pin = app(App\Services\Generation\GenerationProfileFactory::class)->needsPin($scope->workspace->public_id);
if (($pin['product_profile'] ?? null) !== 'current-policy-pool-v1') { throw new RuntimeException('Current-policy profile not enabled in release context.'); }
echo json_encode(['workspace'=>$scope->workspace->public_id,'actor'=>$user->public_id,
    'corpus_generation'=>$scope->activeCorpusGeneration?->public_id,'profile'=>$pin,
    'database_driver'=>config('database.default')], JSON_THROW_ON_ERROR);
