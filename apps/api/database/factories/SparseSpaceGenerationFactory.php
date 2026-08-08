<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmbeddingSpaceGenerationStatus;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\SparseEmbeddingProfile;
use App\Models\SparseSpaceGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SparseSpaceGeneration> */
class SparseSpaceGenerationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'sparse_embedding_profile_id' => SparseEmbeddingProfile::factory(),
            'embedding_space_generation_id' => EmbeddingSpaceGeneration::factory()->available(),
            'vector_name' => 'sparse',
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
        return $this->state(fn (): array => [
            'status' => $status,
            'available_at' => match ($status) {
                EmbeddingSpaceGenerationStatus::Building,
                EmbeddingSpaceGenerationStatus::Verifying => null,
                default => now()->subMinute(),
            },
            'retired_at' => $status === EmbeddingSpaceGenerationStatus::Retired ? now() : null,
        ]);
    }
}
