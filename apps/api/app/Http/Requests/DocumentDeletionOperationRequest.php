<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DocumentDeletionOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $base = [
            'contract_version' => ['required', 'integer', Rule::in([1])],
            'event_id' => ['required', 'uuid'],
            'workspace_id' => ['required', 'uuid'],
            'document_id' => ['required', 'uuid'],
            'lease_token' => ['required', 'uuid'],
        ];

        return array_merge($base, match ($this->route()?->getName()) {
            'document.deletion.complete' => [
                'scopes' => ['required', 'array', 'max:100'],
                'scopes.*.scope_index' => ['required', 'integer', 'min:0', 'distinct'],
                'scopes.*.outcome' => ['required', Rule::in(['verified_clean', 'authoritative_not_found'])],
                'scopes.*.remaining_point_count' => ['required', 'integer', 'min:0'],
            ],
            'document.deletion.fail' => [
                'classification' => ['required', Rule::in(['retryable', 'permanent'])],
                'failure_code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
                'failure_message' => ['required', 'string', 'max:1000'],
            ],
            default => [],
        });
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('event_id') !== $this->route('eventId')) {
                $validator->errors()->add('event_id', 'The event identifier does not match the deletion operation.');
            }
        }];
    }
}
