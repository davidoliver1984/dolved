<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class ListDocumentFamiliesRequest extends FormRequest
{
    private const SORTS = ['title', 'last_meaningful_update', 'review_due_date'];

    private const DIRECTIONS = ['asc', 'desc'];

    private const PAGE_SIZES = [25, 50, 100];

    private const REVIEW_STATES = ['overdue', 'due_soon', 'unassigned'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->string('status')->value();
        $sort = $this->string('sort')->value();
        $direction = $this->string('direction')->value();
        $review = $this->string('review_status')->value();
        $perPage = $this->integer('per_page');
        $this->merge([
            'search' => mb_substr(trim($this->string('search')->value()), 0, 200),
            'status' => DocumentStatus::tryFrom($status)?->value,
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'last_meaningful_update',
            'direction' => in_array($direction, self::DIRECTIONS, true) ? $direction : 'desc',
            'review_status' => in_array($review, self::REVIEW_STATES, true) ? $review : null,
            'category' => Str::isUuid($this->input('category')) ? $this->input('category') : null,
            'applicability' => Str::isUuid($this->input('applicability')) ? $this->input('applicability') : null,
            'owner' => Str::isUuid($this->input('owner')) ? $this->input('owner') : null,
            'per_page' => in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25,
            'historical' => filter_var($this->input('historical', false), FILTER_VALIDATE_BOOL),
            'page' => max(1, $this->integer('page', 1)),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['string', 'max:200'],
            'status' => ['nullable', 'string'],
            'category' => ['nullable', 'uuid'],
            'applicability' => ['nullable', 'uuid'],
            'owner' => ['nullable', 'uuid'],
            'review_status' => ['nullable', 'string'],
            'sort' => ['required', 'string'],
            'direction' => ['required', 'string'],
            'per_page' => ['required', 'integer'],
            'page' => ['required', 'integer', 'min:1'],
            'historical' => ['required', 'boolean'],
        ];
    }
}
