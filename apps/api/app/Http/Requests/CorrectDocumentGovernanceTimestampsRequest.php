<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class CorrectDocumentGovernanceTimestampsRequest extends DocumentGovernanceCommandRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'approved_at' => ['required', 'date'],
            'withdrawn_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
