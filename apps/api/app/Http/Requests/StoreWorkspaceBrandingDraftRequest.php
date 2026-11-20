<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkspaceBrandingDraftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'draft_version' => ['required', 'integer', 'min:0'],
            'configuration' => ['required', 'array'],
            'configuration.display_name' => ['required', 'string', 'min:1', 'max:80'],
            'configuration.logo_label' => ['required', 'string', 'min:1', 'max:120'],
            'configuration.primary_logo' => ['nullable', 'uuid'],
            'configuration.dark_logo' => ['nullable', 'uuid'],
            'configuration.favicon' => ['nullable', 'uuid'],
            'configuration.primary_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'configuration.accent_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'configuration.sign_in_heading' => ['nullable', 'string', 'max:120'],
            'configuration.sign_in_introduction' => ['nullable', 'string', 'max:400'],
            'configuration.chat_welcome_heading' => ['nullable', 'string', 'max:120'],
            'configuration.chat_welcome_introduction' => ['nullable', 'string', 'max:400'],
            'configuration.sender_display_name' => ['nullable', 'string', 'max:80'],
            'configuration.footer_text' => ['nullable', 'string', 'max:300'],
            'configuration.support_contact' => ['nullable', 'email:rfc', 'max:254'],
        ];
    }
}
