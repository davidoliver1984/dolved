<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\EmbeddingProfile;
use App\Models\EmbeddingSpaceGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmbeddingSpaceGeneration>
 */
class EmbeddingSpaceGenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'embedding_profile_id' => EmbeddingProfile::factory(),
            'collection_name' => 'embedding-space-'.fake()->unique()->uuid(),
            'vector_name' => 'dense',
            'dimensions' => 3,
            'distance' => 'cosine',
            'status' => EmbeddingSpaceGenerationStatus::Building,
            'available_at' => null,
            'retired_at' => null,
        ];
    }

    public function verifying(): static
    {
        return $this->lifecycle(EmbeddingSpaceGenerationStatus::Verifying);
    }

    public function available(): static
    {
        return $this->lifecycle(EmbeddingSpaceGenerationStatus::Available);
    }

    public function retiring(): static
    {
        return $this->lifecycle(EmbeddingSpaceGenerationStatus::Retiring);
    }

    public function retired(): static
    {
        return $this->lifecycle(EmbeddingSpaceGenerationStatus::Retired);
    }

    private function lifecycle(EmbeddingSpaceGenerationStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'available_at' => match ($status) {
                EmbeddingSpaceGenerationStatus::Building,
                EmbeddingSpaceGenerationStatus::Verifying => null,
                default => now()->subMinute(),
            },
            'retired_at' => $status === EmbeddingSpaceGenerationStatus::Retired
                ? now()
                : null,
        ]);
    }
}
