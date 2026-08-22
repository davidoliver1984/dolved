<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionEmbeddingSpaceCommand extends Command
{
    protected $signature = 'ingestion:provision-embedding-space';

    protected $description = 'Idempotently provision the accepted V1 embedding space';

    public function handle(): int
    {
        $e2e = app()->environment('e2e');
        $profileIdentity = $e2e ? [
            'fingerprint' => '84dea8351d3ef9effa980965b2e47eaee27a37a4f6e5324f12c2f606625da059',
            'provider' => 'deterministic',
            'model' => 'sha256-unit-vector-v1',
            'model_revision' => '1',
            'adapter_version' => 'deterministic-v1',
            'collection_name' => 'dolved-e2e-vectors-v1',
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
                    'dimensions' => 1024,
                    'output_dtype' => 'float',
                    'document_input_type' => 'document',
                    'query_input_type' => 'query',
                    'normalisation' => 'unit_length',
                    'truncation' => false,
                    'model_revision' => $profileIdentity['model_revision'],
                    'adapter_version' => $profileIdentity['adapter_version'],
                ],
            );
            EmbeddingSpaceGeneration::query()->firstOrCreate(
                ['collection_name' => $profileIdentity['collection_name']],
                [
                    'public_id' => (string) Str::uuid(),
                    'embedding_profile_id' => $profile->id,
                    'vector_name' => 'dense',
                    'dimensions' => 1024,
                    'distance' => 'cosine',
                    'status' => EmbeddingSpaceGenerationStatus::Available,
                    'available_at' => now(),
                ],
            );
        }));
        $this->info('The V1 embedding space is available.');

        return self::SUCCESS;
    }
}
