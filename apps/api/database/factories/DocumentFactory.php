<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publicId = fake()->unique()->uuid();

        return [
            'public_id' => $publicId,
            'workspace_id' => Workspace::factory(),
            'created_by_user_id' => User::factory(),
            'status' => DocumentStatus::Uploading,
            'source_filename' => fake()->word().'.pdf',
            'media_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1, 10_000_000),
            'storage_key' => fn (array $attributes): string => sprintf(
                'workspaces/%s/documents/%s/source',
                Workspace::query()->findOrFail($attributes['workspace_id'])->public_id,
                $publicId,
            ),
            'failure_category' => null,
            'failure_message' => null,
        ];
    }

    public function uploading(): static
    {
        return $this->status(DocumentStatus::Uploading);
    }

    public function uploaded(): static
    {
        return $this->status(DocumentStatus::Uploaded);
    }

    public function queued(): static
    {
        return $this->status(DocumentStatus::Queued);
    }

    public function processing(): static
    {
        return $this->status(DocumentStatus::Processing);
    }

    public function indexed(): static
    {
        return $this->status(DocumentStatus::Indexed);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Failed,
            'failure_category' => 'extraction_failed',
            'failure_message' => 'Synthetic processing failure.',
        ]);
    }

    public function deleting(): static
    {
        return $this->status(DocumentStatus::Deleting);
    }

    public function deleted(): static
    {
        return $this->status(DocumentStatus::Deleted);
    }

    private function status(DocumentStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'failure_category' => null,
            'failure_message' => null,
        ]);
    }
}
