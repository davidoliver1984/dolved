<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SyncDocumentFamilyTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'tag_public_ids' => ['required', 'array', 'max:20'],
            'tag_public_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
