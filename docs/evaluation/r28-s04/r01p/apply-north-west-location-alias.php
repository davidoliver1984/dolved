<?php

declare(strict_types=1);

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$connection = (string) config('database.default');
$database = (string) config("database.connections.{$connection}.database");
if (config('e2e.resource_marker') !== 'dolved-e2e'
    || config('e2e.database_marker') !== 'dolved_e2e_r28_s03'
    || $database !== 'dolved_e2e_r28_s03') {
    throw new RuntimeException('Alias fixture may only be applied to isolated R28-S03 E2E database.');
}

$fixture = json_decode((string) file_get_contents('/tmp/r28-north-west-location-alias.json'), true, flags: JSON_THROW_ON_ERROR);
if (($fixture['workspace_public_id'] ?? null) !== '5323cd6b-8b7a-40f9-98b0-9413c75f643e'
    || ($fixture['location_public_id'] ?? null) !== 'ac53a327-426b-41bf-b4ba-4dc61d7e5d64'
    || ($fixture['location_name'] ?? null) !== 'North West Region'
    || ($fixture['alias'] ?? null) !== 'North West') {
    throw new RuntimeException('Alias fixture identity mismatch.');
}

$workspace = App\Models\Workspace::query()->where('public_id', $fixture['workspace_public_id'])->sole();
$location = App\Models\OrganisationalLocation::query()
    ->where('workspace_id', $workspace->id)
    ->where('public_id', $fixture['location_public_id'])
    ->where('name', $fixture['location_name'])->sole();

$result = Illuminate\Support\Facades\DB::transaction(function () use ($workspace, $location, $fixture): array {
    $existing = App\Models\OrganisationalLocationAlias::query()
        ->where('workspace_id', $workspace->id)
        ->where('normalised_alias', 'north west')->lockForUpdate()->get();
    if ($existing->isNotEmpty()) {
        if ($existing->count() !== 1 || $existing->first()->organisational_location_id !== $location->id) {
            throw new RuntimeException('Alias already belongs to another location in this workspace.');
        }

        return ['status' => 'already_applied', 'alias_id' => $existing->first()->id];
    }
    $alias = new App\Models\OrganisationalLocationAlias(['alias' => $fixture['alias']]);
    $alias->workspace()->associate($workspace);
    $alias->organisationalLocation()->associate($location);
    $alias->save();

    return ['status' => 'created', 'alias_id' => $alias->id];
});

echo json_encode([
    ...$result,
    'workspace_public_id' => $workspace->public_id,
    'location_public_id' => $location->public_id,
    'alias' => $fixture['alias'],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
