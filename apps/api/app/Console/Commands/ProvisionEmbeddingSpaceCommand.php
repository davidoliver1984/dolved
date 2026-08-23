<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use App\Support\E2e\DeterministicRetrievalProfile;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionEmbeddingSpaceCommand extends Command
{
    protected $signature = 'ingestion:provision-embedding-space';

    protected $description = 'Idempotently provision the accepted V1 embedding space';

    public function handle(DeterministicRetrievalProfile $deterministicProfile): int
    {
        $e2e = app()->environment('e2e');
        $e2eProfile = $e2e ? $deterministicProfile->load()['dense'] : null;
        $profileIdentity = $e2e ? [
            ...$e2eProfile['profile'],
            'fingerprint' => $e2eProfile['fingerprint'],
            ...$e2eProfile['space'],
        ] : [
            'fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            'provider' => 'voyage',
            'model' => 'voyage-4-large',
            'model_revision' => null,
            'adapter_version' => '1',
            'collection_name' => 'rag-platform-vectors-v1',
        ];
        Model::unguarded(fn () => DB::transaction(function () use ($profileIdentity): void {
            $profile = EmbeddingProfile::query()->firstOrCreate(
                ['fingerprint' => $profileIdentity['fingerprint']],
                [
                    'public_id' => (string) Str::uuid(),
                    'provider' => $profileIdentity['provider'],
                    'model' => $profileIdentity['model'],
                    'dimensions' => $profileIdentity['dimensions'] ?? 1024,
                    'output_dtype' => $profileIdentity['output_dtype'] ?? 'float',
                    'document_input_type' => $profileIdentity['document_input_type'] ?? 'document',
                    'query_input_type' => $profileIdentity['query_input_type'] ?? 'query',
                    'normalisation' => $profileIdentity['normalisation'] ?? 'unit_length',
                    'truncation' => $profileIdentity['truncation'] ?? false,
                    'model_revision' => $profileIdentity['model_revision'],
                    'adapter_version' => $profileIdentity['adapter_version'],
                ],
            );
            EmbeddingSpaceGeneration::query()->firstOrCreate(
                ['collection_name' => $profileIdentity['collection_name']],
                [
                    'public_id' => (string) Str::uuid(),
                    'embedding_profile_id' => $profile->id,
                    'vector_name' => $profileIdentity['vector_name'] ?? 'dense',
                    'dimensions' => $profileIdentity['dimensions'] ?? 1024,
                    'distance' => $profileIdentity['distance'] ?? 'cosine',
                    'status' => EmbeddingSpaceGenerationStatus::Available,
                    'available_at' => now(),
                ],
            );
        }));
        $this->info('The V1 embedding space is available.');

        return self::SUCCESS;
    }
}
