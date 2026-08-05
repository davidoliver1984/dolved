<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkspaceCorpusGenerationStatus;
use App\Models\EmbeddingSpaceGeneration;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceCorpusGeneration>
 */
class WorkspaceCorpusGenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'workspace_id' => Workspace::factory(),
            'embedding_space_generation_id' => EmbeddingSpaceGeneration::factory()->available(),
            'status' => WorkspaceCorpusGenerationStatus::Building,
            'activated_at' => null,
            'superseded_at' => null,
            'retired_at' => null,
        ];
    }

    public function verifying(): static
    {
        return $this->lifecycle(WorkspaceCorpusGenerationStatus::Verifying);
    }

    public function active(): static
    {
        return $this->lifecycle(WorkspaceCorpusGenerationStatus::Active);
    }

    public function superseded(): static
    {
        return $this->lifecycle(WorkspaceCorpusGenerationStatus::Superseded);
    }

    public function retired(): static
    {
        return $this->lifecycle(WorkspaceCorpusGenerationStatus::Retired);
    }

    private function lifecycle(WorkspaceCorpusGenerationStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'activated_at' => match ($status) {
                WorkspaceCorpusGenerationStatus::Building,
                WorkspaceCorpusGenerationStatus::Verifying => null,
                default => now()->subMinutes(2),
            },
            'superseded_at' => $status === WorkspaceCorpusGenerationStatus::Superseded
                ? now()->subMinute()
                : null,
            'retired_at' => $status === WorkspaceCorpusGenerationStatus::Retired
                ? now()
                : null,
        ]);
    }
}
