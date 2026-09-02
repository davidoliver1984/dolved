<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeDocumentFamilyOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'expected_owner_public_id' => ['required', 'uuid'],
            'expected_owner_assignment_generation' => ['required', 'integer', 'min:1'],
            'intended_owner_public_id' => ['required', 'uuid'],
        ];
    }
}
