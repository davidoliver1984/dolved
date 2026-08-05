<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $configuration = [
            'strategy' => 'baseline-structural',
            'version' => '1',
            'target_tokens' => 400,
        ];

        return [
            'public_id' => fake()->unique()->uuid(),
            'workspace_id' => Workspace::factory(),
            'document_id' => fn (array $attributes): int => Document::factory()
                ->for(Workspace::query()->findOrFail($attributes['workspace_id']))
                ->create()
                ->id,
            'ordinal' => fake()->unique()->numberBetween(0, 1_000_000),
            'text' => fake()->paragraph(),
            'token_count' => fake()->numberBetween(1, 512),
            'strategy_name' => 'baseline-structural',
            'strategy_version' => '1',
            'configuration' => $configuration,
            'configuration_fingerprint' => hash(
                'sha256',
                json_encode($configuration, JSON_THROW_ON_ERROR)
            ),
            'provenance' => [[
                'normalised_element_id' => fake()->uuid(),
                'source_element_ids' => [fake()->uuid()],
                'source_locations' => [[
                    'type' => 'text',
                    'start_character' => 0,
                    'end_character' => 20,
                    'start_line' => 1,
                    'end_line' => 1,
                ]],
                'element_start_character' => 0,
                'element_end_character' => 20,
                'chunk_start_character' => 0,
                'chunk_end_character' => 20,
                'role' => 'primary',
            ]],
        ];
    }
}
