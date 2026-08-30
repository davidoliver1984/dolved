<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class DocumentVersionResource extends DocumentResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'family_public_id' => $this->whenLoaded('family', fn (): ?string => $this->family?->public_id),
            'predecessor_public_id' => $this->whenLoaded('predecessor', fn (): ?string => $this->predecessor?->public_id),
            'governance_status' => $this->governance_status->value,
            'effective_from' => $this->effective_from,
            'approved_at' => $this->approved_at,
            'withdrawn_at' => $this->withdrawn_at,
            'is_current_authority' => (bool) $this->getAttribute('is_current_authority'),
            'extraction_warning_count' => $this->activeExtractionProjectionGeneration?->expected_warning_count ?? 0,
            'capabilities' => $this->getAttribute('capabilities') ?? [
                'approve' => false,
                'withdraw' => false,
                'reschedule' => false,
                'create_applicability_successor' => false,
                'correct_timestamps' => false,
            ],
            'applicability' => $this->whenLoaded('applicabilitySnapshot', fn (): array => [
                'scope' => $this->applicabilitySnapshot->scope->value,
                'locations' => $this->applicabilitySnapshot->locations->map(fn ($location): array => [
                    'public_id' => $location->public_id,
                    'name' => $location->name,
                ])->values()->all(),
            ]),
        ];
    }
}
