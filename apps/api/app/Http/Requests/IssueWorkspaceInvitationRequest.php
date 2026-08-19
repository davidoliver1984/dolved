<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueWorkspaceInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:254'],
            'role' => ['required', Rule::in([WorkspaceRole::Member->value, WorkspaceRole::Admin->value])],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
