<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetrieveWorkspaceEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:1', 'max:8000'],
            'candidate_k' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.config('retrieval.candidate_k_max'),
            ],
        ];
    }
}
