<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SparseEmbeddingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SparseEmbeddingProfile> */
class SparseEmbeddingProfileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'provider' => 'test',
            'model' => 'deterministic-sparse-test',
            'tokenizer' => 'test-tokenizer',
            'tokenizer_revision' => '1',
            'output_representation' => 'sparse-index-weight',
            'max_input_tokens' => 512,
            'document_input_type' => 'document',
            'query_input_type' => 'query',
            'model_revision' => 'efcd182bc7eb351e81a9445752d4388c2bab500b',
            'adapter_version' => '1',
        ];
    }

    public function spladeV1(): static
    {
        return $this->state(fn (): array => [
            'provider' => 'fastembed',
            'model' => 'prithivida/Splade_PP_en_v1',
            'tokenizer' => 'bert-base-uncased',
            'tokenizer_revision' => null,
            'output_representation' => 'sparse-index-weight',
            'max_input_tokens' => 512,
            'document_input_type' => 'document',
            'query_input_type' => 'query',
            'model_revision' => null,
            'adapter_version' => '1',
        ]);
    }
}
