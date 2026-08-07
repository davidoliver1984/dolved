<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrganisationalLocation;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganisationalLocation> */
class OrganisationalLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'workspace_id' => Workspace::factory(),
            'parent_id' => null,
            'name' => fake()->city(),
            'kind' => fake()->randomElement(['country', 'region', 'site', 'department']),
        ];
    }
}
