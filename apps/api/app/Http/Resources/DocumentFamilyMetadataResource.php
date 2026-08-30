<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DocumentFamilyMetadataResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'review_due_date' => $this->review_due_date?->toDateString(),
            'category' => $this->category === null ? null : new DocumentCategoryResource($this->category),
            'owner' => $this->owner === null ? null : [
                'public_id' => $this->owner->public_id,
                'name' => $this->owner->name,
            ],
            'tags' => DocumentTagResource::collection($this->whenLoaded('tags')),
            'capabilities' => $this->getAttribute('capabilities') ?? ['edit' => false],
            'edit_options' => $this->getAttribute('edit_options'),
        ];
    }
}
