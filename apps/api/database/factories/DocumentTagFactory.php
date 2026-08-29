<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentTag;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentTag> */
class DocumentTagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
