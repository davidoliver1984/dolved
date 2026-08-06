<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\IngestionEventClaim;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngestionEventClaim>
 */
class IngestionEventClaimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => fake()->unique()->uuid(),
            'document_id' => Document::factory(),
            'workspace_id' => fn (array $attributes): int => Document::query()->findOrFail($attributes['document_id'])->workspace_id,
            'workspace_public_id' => fn (array $attributes): string => Workspace::query()->findOrFail($attributes['workspace_id'])->public_id,
            'document_public_id' => fn (array $attributes): string => Document::query()->findOrFail($attributes['document_id'])->public_id,
            'correlation_id' => fake()->uuid(),
            'payload_sha256' => hash('sha256', fake()->unique()->sentence()),
            'claimed_at' => now(),
        ];
    }
}
