<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmbeddingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmbeddingProfile>
 */
class EmbeddingProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'provider' => 'test',
            'model' => 'deterministic-test',
            'dimensions' => 3,
            'output_dtype' => 'float',
            'document_input_type' => 'document',
            'query_input_type' => 'query',
            'normalisation' => 'unit_length',
            'truncation' => false,
            'model_revision' => null,
            'adapter_version' => '1',
        ];
    }

    public function voyageV1(): static
    {
        return $this->state(fn (array $attributes): array => [
            'fingerprint' => 'ac57bb349ef16e2977756edaf39945974797da2339307510209e6ae402cbb86c',
            'provider' => 'voyage',
            'model' => 'voyage-4-large',
            'dimensions' => 1024,
            'output_dtype' => 'float',
            'document_input_type' => 'document',
            'query_input_type' => 'query',
            'normalisation' => 'unit_length',
            'truncation' => false,
            'model_revision' => null,
            'adapter_version' => '1',
        ]);
    }
}
