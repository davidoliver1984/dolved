<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceCorpusGeneration;
use App\Models\WorkspaceCorpusGenerationRollback;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkspaceCorpusGenerationRollback> */
class WorkspaceCorpusGenerationRollbackFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'workspace_id' => Workspace::factory(),
            'demoted_generation_id' => WorkspaceCorpusGeneration::factory(),
            'promoted_generation_id' => WorkspaceCorpusGeneration::factory(),
            'actor_user_id' => User::factory(),
            'reason' => 'Synthetic rollback for testing.',
            'occurred_at' => now(),
        ];
    }
}
