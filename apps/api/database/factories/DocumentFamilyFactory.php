<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentFamily;
use App\Models\User;
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
            'description' => null,
            'category_id' => null,
            'owner_user_id' => User::factory(),
            'review_due_date' => null,
        ];
    }
}
