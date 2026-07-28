<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceMembership>
 */
class WorkspaceMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => WorkspaceRole::Owner,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => WorkspaceRole::Admin,
        ]);
    }

    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => WorkspaceRole::Member,
        ]);
    }
}
