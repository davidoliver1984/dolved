<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmbeddingSpaceGeneration;
use App\Support\E2e\DeterministicRetrievalProfile;
use Illuminate\Console\Command;

class ProvisionE2eRetrievalCommand extends Command
{
    protected $signature = 'e2e:provision-retrieval';

    protected $description = 'Provision the deterministic E2E dense and sparse retrieval profile';

    public function handle(DeterministicRetrievalProfile $deterministicProfile): int
    {
        $this->assertE2eIdentity();

        if ($this->callSilently('ingestion:provision-embedding-space') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $collectionName = $deterministicProfile->load()['dense']['space']['collection_name'];
        if (! is_string($collectionName)) {
            throw new \RuntimeException('The E2E dense collection identity is invalid.');
        }
        $dense = EmbeddingSpaceGeneration::query()
            ->where('collection_name', $collectionName)
            ->firstOrFail();

        if ($this->callSilently('retrieval:provision-sparse-space', [
            'embedding-space' => $dense->public_id,
        ]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->line(json_encode([
            'embedding_space_generation_id' => $dense->public_id,
            'identity' => 'dolved-e2e',
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function assertE2eIdentity(): void
    {
        if (! app()->environment('e2e')) {
            throw new \RuntimeException('E2E retrieval provisioning requires APP_ENV=e2e.');
        }

        $resourceMarker = (string) config('e2e.resource_marker');
        $databaseMarker = (string) config('e2e.database_marker');
        $databaseName = (string) config('database.connections.'.config('database.default').'.database');

        if ($resourceMarker !== 'dolved-e2e' || $databaseMarker === '' || ! str_contains($databaseName, $databaseMarker)) {
            throw new \RuntimeException('E2E retrieval provisioning refused an ambiguous resource/database identity.');
        }
    }
}
