<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DocumentDeletionStatus;
use App\Enums\DocumentStatus;
use Illuminate\Http\Request;

class DocumentAdministrationResource extends DocumentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $attempt = $this->relationLoaded('latestIngestionAttempt')
            ? $this->latestIngestionAttempt
            : null;

        return [
            ...parent::toArray($request),
            'governance_status' => $this->governance_status->value,
            'failure_category' => $this->failure_category,
            'failure_message' => $this->failure_message,
            'extraction_warnings' => ($attempt?->publication_evidence ?? [])['warnings'] ?? [],
            'created_by' => $this->whenLoaded('createdBy', fn (): array => [
                'name' => $this->createdBy?->name,
            ]),
            'deletion' => $this->whenLoaded('deletionOperation', fn (): ?array => $this->deletionOperation === null ? null : [
                'public_id' => $this->deletionOperation->public_id,
                'status' => $this->deletionOperation->status->value,
                'failure_code' => $this->deletionOperation->failure_code,
                'stuck' => $this->deletionOperation->status === DocumentDeletionStatus::Failed
                    || (
                        $this->deletionOperation->status !== DocumentDeletionStatus::Completed
                        && $this->deletionOperation->updated_at->lte(
                            now()->subSeconds(max(60, 2 * (int) config('ingestion.orchestration.lease_seconds'))),
                        )
                    ),
            ]),
            'capabilities' => [
                'retry' => $request->user()?->can('retry', $this->resource) === true
                    && $this->status === DocumentStatus::Failed,
                'delete' => $request->user()?->can('delete', $this->resource) === true
                    && ! in_array($this->status, [DocumentStatus::Deleting, DocumentStatus::Deleted], true),
            ],
        ];
    }
}
