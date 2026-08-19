<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IngestionOperationRequest extends FormRequest
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
            'ingestion.chunks.submit' => [
                'chunks' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('ingestion.orchestration.chunk_batch_size'))],
                'chunks.*.chunk_id' => ['required', 'uuid'],
                'chunks.*.ordinal' => ['required', 'integer', 'min:0'],
                'chunks.*.text' => ['required', 'string', 'not_regex:/^\s*$/u'],
                'chunks.*.token_count' => ['required', 'integer', 'min:1'],
                'chunks.*.strategy_name' => ['required', 'string', 'max:100'],
                'chunks.*.strategy_version' => ['required', 'string', 'max:100'],
                'chunks.*.configuration' => ['required', 'array'],
                'chunks.*.configuration_fingerprint' => ['required', 'regex:/^[0-9a-f]{64}$/'],
                'chunks.*.provenance' => ['required', 'array', 'min:1'],
                'chunks.*.provenance.*.normalised_element_id' => ['required', 'uuid'],
                'chunks.*.provenance.*.source_element_ids' => ['required', 'array', 'min:1'],
                'chunks.*.provenance.*.source_element_ids.*' => ['required', 'uuid'],
                'chunks.*.provenance.*.source_locations' => ['required', 'array', 'min:1'],
                'chunks.*.provenance.*.source_locations.*' => ['required', 'array'],
                'chunks.*.provenance.*.source_locations.*.kind' => ['required', 'string', Rule::in(['text', 'pdf', 'docx'])],
                'chunks.*.provenance.*.source_locations.*.start_character' => [
                    'nullable', 'integer', 'min:0',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,text',
                    'required_with:chunks.*.provenance.*.source_locations.*.end_character',
                ],
                'chunks.*.provenance.*.source_locations.*.end_character' => [
                    'nullable', 'integer', 'min:0',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,text',
                    'required_with:chunks.*.provenance.*.source_locations.*.start_character',
                ],
                'chunks.*.provenance.*.source_locations.*.start_line' => [
                    'integer', 'min:1',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,text',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,text',
                ],
                'chunks.*.provenance.*.source_locations.*.end_line' => [
                    'integer', 'min:1',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,text',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,text',
                ],
                'chunks.*.provenance.*.source_locations.*.page_number' => [
                    'integer', 'min:1',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.page_width' => [
                    'numeric', 'gt:0',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.page_height' => [
                    'numeric', 'gt:0',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.x0' => [
                    'numeric',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.top' => [
                    'numeric',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.x1' => [
                    'numeric',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.bottom' => [
                    'numeric',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.rotation_degrees' => [
                    'integer', Rule::in([0, 90, 180, 270]),
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.has_distinct_crop_box' => [
                    'boolean',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,pdf',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,pdf',
                ],
                'chunks.*.provenance.*.source_locations.*.body_block_index' => [
                    'integer', 'min:0',
                    'required_if:chunks.*.provenance.*.source_locations.*.kind,docx',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,docx',
                ],
                'chunks.*.provenance.*.source_locations.*.table_row_index' => [
                    'nullable', 'integer', 'min:0',
                    'required_with:chunks.*.provenance.*.source_locations.*.table_column_index',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,docx',
                ],
                'chunks.*.provenance.*.source_locations.*.table_column_index' => [
                    'nullable', 'integer', 'min:0',
                    'required_with:chunks.*.provenance.*.source_locations.*.table_row_index',
                    'prohibited_unless:chunks.*.provenance.*.source_locations.*.kind,docx',
                ],
                'chunks.*.provenance.*.element_start_character' => ['required', 'integer', 'min:0'],
                'chunks.*.provenance.*.element_end_character' => ['required', 'integer', 'min:1'],
                'chunks.*.provenance.*.chunk_start_character' => ['required', 'integer', 'min:0'],
                'chunks.*.provenance.*.chunk_end_character' => ['required', 'integer', 'min:1'],
                'chunks.*.provenance.*.role' => ['required', Rule::in(['primary', 'overlap'])],
                'chunks.*.content_digest' => ['required', 'regex:/^[0-9a-f]{64}$/'],
            ],
            'ingestion.chunks.seal' => [
                'expected_chunk_count' => ['required', 'integer', 'min:1'],
                'chunk_manifest_digest' => ['required', 'regex:/^[0-9a-f]{64}$/'],
                'configuration_fingerprint' => ['required', 'regex:/^[0-9a-f]{64}$/'],
            ],
            'ingestion.attempt.resume' => [
                'cursor' => ['sometimes', 'integer', 'min:0'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:'.max(1, (int) config('ingestion.orchestration.resume_page_size'))],
            ],
            'ingestion.publication.authorise', 'ingestion.complete' => [
                'expected_chunk_count' => ['required', 'integer', 'min:1'],
                'chunk_manifest_digest' => ['required', 'regex:/^[0-9a-f]{64}$/'],
                'point_manifest_digest' => ['required', 'regex:/^[0-9a-f]{64}$/'],
                'expected_point_count' => ['required', 'integer', 'min:1'],
                'point_ids' => ['required', 'array', 'min:1'],
                'point_ids.*' => ['required', 'uuid', 'distinct'],
                'embedding_profile_fingerprint' => ['required', 'regex:/^[0-9a-f]{64}$/'],
                'sparse_profile_fingerprint' => ['nullable', 'regex:/^[0-9a-f]{64}$/'],
                'embedding_space_generation_id' => ['required', 'uuid'],
                'sparse_space_generation_id' => ['nullable', 'uuid'],
                'workspace_corpus_generation_id' => ['required', 'uuid'],
                'publication_verified' => [Rule::requiredIf($this->route()?->getName() === 'ingestion.complete'), 'boolean'],
                'warnings' => ['sometimes', 'array', 'max:20'],
                'warnings.*.code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
                'warnings.*.message' => ['required', 'string', 'max:500'],
            ],
            'ingestion.fail' => [
                'classification' => ['required', Rule::in(['permanent'])],
                'failure_code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
                'failure_message' => ['required', 'string', 'max:1000'],
            ],
            'ingestion.attempt.cancel' => [],
            default => [],
        });
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('event_id') !== $this->route('eventId')) {
                $validator->errors()->add('event_id', 'The event identifier does not match the requested attempt.');
            }
            if (
                $this->route()?->getName() === 'ingestion.chunks.submit'
                && strlen($this->getContent()) > max(1, (int) config('ingestion.orchestration.chunk_body_bytes'))
            ) {
                $validator->errors()->add('chunks', 'The chunk submission body exceeds the configured byte limit.');
            }

            if ($this->route()?->getName() === 'ingestion.chunks.submit') {
                $this->validateSourceLocations($validator);
            }
        }];
    }

    private function validateSourceLocations(Validator $validator): void
    {
        $chunkKeys = [
            'chunk_id', 'ordinal', 'text', 'token_count', 'strategy_name', 'strategy_version',
            'configuration', 'configuration_fingerprint', 'provenance', 'content_digest',
        ];
        $provenanceKeys = [
            'normalised_element_id', 'source_element_ids', 'source_locations',
            'element_start_character', 'element_end_character', 'chunk_start_character',
            'chunk_end_character', 'role',
        ];
        $allowedKeys = [
            'text' => ['kind', 'start_character', 'end_character', 'start_line', 'end_line'],
            'pdf' => [
                'kind', 'page_number', 'page_width', 'page_height', 'x0', 'top', 'x1', 'bottom',
                'rotation_degrees', 'has_distinct_crop_box', 'start_character', 'end_character',
            ],
            'docx' => [
                'kind', 'body_block_index', 'table_row_index', 'table_column_index',
                'start_character', 'end_character',
            ],
        ];

        foreach ($this->input('chunks', []) as $chunkIndex => $chunk) {
            if (is_array($chunk) && array_diff(array_keys($chunk), $chunkKeys) !== []) {
                $validator->errors()->add("chunks.{$chunkIndex}", 'The chunk contains unsupported fields.');
            }
            foreach (($chunk['provenance'] ?? []) as $provenanceIndex => $provenance) {
                if (is_array($provenance) && array_diff(array_keys($provenance), $provenanceKeys) !== []) {
                    $validator->errors()->add(
                        "chunks.{$chunkIndex}.provenance.{$provenanceIndex}",
                        'The provenance contribution contains unsupported fields.',
                    );
                }
                foreach (($provenance['source_locations'] ?? []) as $locationIndex => $location) {
                    if (! is_array($location)) {
                        continue;
                    }

                    $path = "chunks.{$chunkIndex}.provenance.{$provenanceIndex}.source_locations.{$locationIndex}";
                    $kind = $location['kind'] ?? null;
                    if (is_string($kind) && isset($allowedKeys[$kind])) {
                        $unknownKeys = array_diff(array_keys($location), $allowedKeys[$kind]);
                        if ($unknownKeys !== []) {
                            $validator->errors()->add($path, 'The source location contains unsupported fields.');
                        }
                    }

                    $this->validateOrderedPair($validator, $location, $path, 'start_character', 'end_character');
                    if ($kind === 'text') {
                        $this->validateOrderedPair($validator, $location, $path, 'start_line', 'end_line');
                    }
                    if ($kind === 'pdf') {
                        $this->validateOrderedPair($validator, $location, $path, 'x0', 'x1');
                        $this->validateOrderedPair($validator, $location, $path, 'top', 'bottom');
                        $this->validateOptionalPair($validator, $location, $path, 'start_character', 'end_character');
                        if (array_key_exists('has_distinct_crop_box', $location) && ! is_bool($location['has_distinct_crop_box'])) {
                            $validator->errors()->add("{$path}.has_distinct_crop_box", 'The has_distinct_crop_box must be a boolean.');
                        }
                    }
                    if ($kind === 'docx') {
                        $this->validateOptionalPair($validator, $location, $path, 'start_character', 'end_character');
                        $this->validateOptionalPair($validator, $location, $path, 'table_row_index', 'table_column_index');
                    }
                }
            }
        }
    }

    /** @param array<string, mixed> $location */
    private function validateOrderedPair(
        Validator $validator,
        array $location,
        string $path,
        string $start,
        string $end,
    ): void {
        if (
            isset($location[$start], $location[$end])
            && is_numeric($location[$start])
            && is_numeric($location[$end])
            && (float) $location[$end] < (float) $location[$start]
        ) {
            $validator->errors()->add("{$path}.{$end}", "The {$end} must not precede {$start}.");
        }
    }

    /** @param array<string, mixed> $location */
    private function validateOptionalPair(
        Validator $validator,
        array $location,
        string $path,
        string $first,
        string $second,
    ): void {
        $firstExists = array_key_exists($first, $location);
        $secondExists = array_key_exists($second, $location);
        if ($firstExists !== $secondExists) {
            $validator->errors()->add("{$path}.{$second}", "The {$first} and {$second} must be provided together.");

            return;
        }

        if ($firstExists && (($location[$first] === null) !== ($location[$second] === null))) {
            $validator->errors()->add("{$path}.{$second}", "The {$first} and {$second} must both be null or both contain values.");
        }
    }
}
