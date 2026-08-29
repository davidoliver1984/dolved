<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class CreateApplicabilityOnlySuccessorRequest extends DocumentGovernanceCommandRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'effective_from' => ['required', 'date'],
            'location_public_ids' => ['required', 'array', 'max:100'],
            'location_public_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
