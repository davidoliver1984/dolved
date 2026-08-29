<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class RescheduleDocumentVersionRequest extends DocumentGovernanceCommandRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'effective_from' => ['required', 'date'],
        ];
    }
}
