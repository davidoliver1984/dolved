<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'public_id' => fake()->unique()->uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'created_by_user_id' => User::factory(),
        ];
    }

    public function withOwner(?User $owner = null): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'created_by_user_id' => $owner ?? User::factory(),
            ])
            ->afterCreating(function (Workspace $workspace) use ($owner): void {
                WorkspaceMembership::factory()
                    ->for($workspace)
                    ->for($owner ?? $workspace->creator)
                    ->owner()
                    ->create();
            });
    }
}
