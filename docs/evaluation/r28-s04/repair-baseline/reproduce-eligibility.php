<?php
// Read-only isolated-runtime replay. No provider dispatch or database writes.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::statement('SET default_transaction_read_only = on');
$workspace = App\Models\Workspace::where('public_id', '5323cd6b-8b7a-40f9-98b0-9413c75f643e')->firstOrFail();
$membership = $workspace->memberships()->firstOrFail();
$scope = app(App\Queries\Retrieval\BuildAuthorisedKnowledgeScope::class)->handle($membership->user, $workspace->public_id);
$resolver = app(App\Services\Retrieval\EligibilityResolver::class);
$location = new ReflectionMethod($resolver, 'resolveLocationReference');
$reason = $location->invoke($resolver, $scope, 'workplace');
if ($reason !== App\Enums\EligibilityClarificationReason::UnresolvedLocationReference) {
    throw new RuntimeException('Baseline location behaviour changed');
}
$make = function (int $id, int $family) {
    $document = new App\Models\Document;
    $document->setRawAttributes(['id' => $id, 'document_family_id' => $family]);
    return $document;
};
$pair = new ReflectionMethod($resolver, 'distinctComparisonVersions');
[$primary, $comparison] = $pair->invoke($resolver, collect([$make(2, 10), $make(3, 11)]), collect([$make(1, 10)]));
if ($primary->pluck('id')->all() !== [2] || $comparison->pluck('id')->all() !== [1]) {
    throw new RuntimeException('Baseline pairing behaviour changed');
}
echo json_encode(['generic_location' => $reason->value, 'primary_after_pair_filter' => $primary->pluck('id'), 'comparison_after_pair_filter' => $comparison->pluck('id'), 'current_support_removed' => 3, 'scope_authorisation' => 'BuildAuthorisedKnowledgeScope', 'database' => 'read_only'], JSON_PRETTY_PRINT).PHP_EOL;
