<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDocumentFamilyMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[^\p{C}]+$/u'],
            'description' => ['nullable', 'string', 'max:5000', 'regex:/^[^\p{C}]*$/u'],
            'category_public_id' => ['nullable', 'uuid'],
            'owner_public_id' => ['required', 'uuid'],
            'review_due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
