<?php

declare(strict_types=1);

namespace App\Support\Documents;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SavedViewDefinition
{
    public const MAX_BYTES = 8192;

    /** @var list<string> */
    private const ROOT_KEYS = ['search', 'filters', 'sort', 'direction', 'page_size', 'historical'];

    /** @var list<string> */
    private const FILTER_KEYS = ['category', 'applicability', 'owner', 'review_status', 'status'];

    /** @param array<string, mixed> $value @return array<string, mixed> */
    public static function forWrite(array $value): array
    {
        if (array_diff(array_keys($value), self::ROOT_KEYS) !== []) {
            throw ValidationException::withMessages(['definition' => 'The saved view contains unsupported fields.']);
        }

        validator($value, [
            'search' => ['sometimes', 'string', 'max:200'],
            'filters' => ['sometimes', 'array:'.implode(',', self::FILTER_KEYS)],
            'filters.category' => ['sometimes', 'nullable', 'uuid'],
            'filters.applicability' => ['sometimes', 'nullable', 'uuid'],
            'filters.owner' => ['sometimes', 'nullable', 'uuid'],
            'filters.review_status' => ['sometimes', Rule::in(['unassigned', 'overdue', 'due_soon'])],
            'filters.status' => ['sometimes', Rule::in(['uploading', 'uploaded', 'queued', 'processing', 'indexed', 'failed'])],
            'sort' => ['sometimes', Rule::in(['last_meaningful_update', 'title', 'review_due_date'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page_size' => ['sometimes', Rule::in([25, 50, 100])],
            'historical' => ['sometimes', 'boolean'],
        ])->validate();

        $canonical = self::canonical($value);
        if (strlen(json_encode($canonical, JSON_THROW_ON_ERROR)) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['definition' => 'The saved view is too large.']);
        }

        return $canonical;
    }

    /** @param array<string, mixed> $value @return array{definition: array<string, mixed>, notices: list<string>} */
    public static function forOpen(array $value, int $schemaVersion): array
    {
        $notices = [];
        if ($schemaVersion !== 1) {
            return ['definition' => [], 'notices' => ["Saved-view schema version {$schemaVersion} is not supported; its filters were removed."]];
        }

        foreach (array_diff(array_keys($value), self::ROOT_KEYS) as $key) {
            unset($value[$key]);
            $notices[] = "Unsupported saved-view field '{$key}' was removed.";
        }
        if (isset($value['filters']) && is_array($value['filters'])) {
            foreach (array_diff(array_keys($value['filters']), self::FILTER_KEYS) as $key) {
                unset($value['filters'][$key]);
                $notices[] = "Unsupported saved-view filter '{$key}' was removed.";
            }
        }

        try {
            $value = self::forWrite($value);
        } catch (ValidationException) {
            return ['definition' => [], 'notices' => ['The saved view contains unsupported values; its filters were removed.']];
        }

        return ['definition' => $value, 'notices' => $notices];
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private static function canonical(array $value): array
    {
        $canonical = [];
        foreach (self::ROOT_KEYS as $key) {
            if (! array_key_exists($key, $value)) {
                continue;
            }
            if ($key === 'filters' && is_array($value[$key])) {
                $filters = [];
                foreach (self::FILTER_KEYS as $filter) {
                    if (array_key_exists($filter, $value[$key])) {
                        $filters[$filter] = $value[$key][$filter];
                    }
                }
                $canonical[$key] = $filters;

                continue;
            }
            $canonical[$key] = $value[$key];
        }

        return $canonical;
    }
}
