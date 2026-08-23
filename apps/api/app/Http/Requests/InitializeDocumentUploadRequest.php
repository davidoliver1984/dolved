<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InitializeDocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, Closure|string|object>>
     */
    public function rules(): array
    {
        $mimeTypes = collect(config('documents.formats'))
            ->flatten()
            ->unique()
            ->values()
            ->all();

        return [
            'filename' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\p{C}]+$/u',
                'not_regex:/[\\/\\\\]/u',
            ],
            'size_bytes' => [
                'required',
                'integer',
                'min:1',
                'max:'.config('documents.max_upload_bytes'),
            ],
            'media_type' => [
                'required',
                'string',
                'max:255',
                Rule::in($mimeTypes),
            ],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $extension = $this->extension();
                $formats = config('documents.formats');

                if (! array_key_exists($extension, $formats)) {
                    $validator->errors()->add(
                        'filename',
                        'The selected document format is not supported.',
                    );

                    return;
                }

                if (! in_array($this->string('media_type')->value(), $formats[$extension], true)) {
                    $validator->errors()->add(
                        'media_type',
                        'The declared media type does not match the document extension.',
                    );
                }
            },
        ];
    }

    public function extension(): string
    {
        return strtolower(pathinfo(
            $this->string('filename')->value(),
            PATHINFO_EXTENSION,
        ));
    }
}
