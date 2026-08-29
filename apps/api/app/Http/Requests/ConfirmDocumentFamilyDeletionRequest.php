<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmDocumentFamilyDeletionRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'confirmation_digest' => ['required', 'string', 'max:2048'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
