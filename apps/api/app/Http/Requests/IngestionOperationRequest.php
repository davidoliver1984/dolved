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
                'chunks.*.provenance.*.source_locations.*.kind' => ['required', 'string', Rule::in(['text', 'pdf', 'docx'])],
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
                'embedding_space_generation_id' => ['required', 'uuid'],
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
        }];
    }
}
