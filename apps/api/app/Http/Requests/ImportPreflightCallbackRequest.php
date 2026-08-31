<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ImportPreflightCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contract_version' => ['required', 'string'],
            'event_id' => ['required', 'uuid'],
            'workspace_id' => ['required', 'uuid'],
            'import_item_id' => ['required', 'uuid'],
            'staged_object_key' => ['required', 'string'],
            'lease_token' => ['required', 'uuid'],
            'lease_generation' => ['required', 'integer', 'min:1'],
            'result' => ['sometimes', 'string'],
            'diagnostic_code' => ['required', 'string'],
            'source_checksum_sha256' => ['sometimes', 'string'],
            'media_type' => ['sometimes', 'string'],
            'size_bytes' => ['sometimes', 'integer'],
        ];
    }
}
