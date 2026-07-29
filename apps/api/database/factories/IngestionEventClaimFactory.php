<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IngestionEventClaim;
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
            'workspace_public_id' => fake()->uuid(),
            'document_public_id' => fake()->uuid(),
            'correlation_id' => fake()->uuid(),
            'payload_sha256' => hash('sha256', fake()->unique()->sentence()),
            'claimed_at' => now(),
        ];
    }
}
