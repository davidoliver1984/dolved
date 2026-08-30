<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DocumentFamilyLibraryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $current = $this->getRelation('currentDocument');
        $state = $this->state($current);

        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category === null ? null : new DocumentCategoryResource($this->category),
            'tags' => DocumentTagResource::collection($this->whenLoaded('tags')),
            'owner' => $this->owner === null ? ['name' => 'Needs reassignment', 'needs_reassignment' => true] : [
                'public_id' => $this->owner->public_id,
                'name' => $this->owner->name,
                'needs_reassignment' => false,
            ],
            'review_due_date' => $this->review_due_date?->toDateString(),
            'last_meaningful_update' => $this->last_meaningful_update,
            'state' => $state,
            'scheduled_effective_from' => $this->scheduled_effective_from,
            'version_count' => (int) $this->version_count,
            'historical' => $current === null && (int) $this->version_count > 0 && (int) $this->draft_count === 0 && $this->scheduled_effective_from === null,
            'current_version' => $current instanceof Document ? [
                'public_id' => $current->public_id,
                'technical_status' => $current->status->value,
                'source_filename' => $current->source_filename,
                'media_type' => $current->media_type,
                'size_bytes' => $current->size_bytes,
                'checksum_verification_status' => $current->checksum_verification_status->value,
                'governance_status' => $current->governance_status->value,
                'effective_from' => $current->effective_from?->toIso8601String(),
                'approved_at' => $current->approved_at?->toIso8601String(),
                'withdrawn_at' => $current->withdrawn_at?->toIso8601String(),
                'extraction_warning_count' => $current->activeExtractionProjectionGeneration?->expected_warning_count ?? 0,
            ] : null,
        ];
    }

    private function state(mixed $current): string
    {
        if ($current instanceof Document) {
            return 'current';
        }
        if ($this->scheduled_effective_from !== null) {
            return 'scheduled';
        }
        if ((int) $this->draft_count > 0) {
            return $this->pending_status ?? 'draft';
        }

        return 'historical';
    }
}
