<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentFamily;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentFamily> */
class DocumentFamilyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(3, true),
        ];
    }
}
