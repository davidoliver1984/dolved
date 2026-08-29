<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentCategoryStatus;
use App\Models\DocumentCategory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentCategory> */
class DocumentCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->words(2, true),
            'status' => DocumentCategoryStatus::Active,
        ];
    }
}
