<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:200'],
            'status' => ['sometimes', Rule::enum(DocumentStatus::class)],
            'governance_status' => ['sometimes', Rule::enum(DocumentGovernanceStatus::class)],
            'created_by_user_id' => ['sometimes', 'integer', 'min:1'],
            'created_from' => ['sometimes', 'date'],
            'created_to' => ['sometimes', 'date', 'after_or_equal:created_from'],
            'failure_category' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
