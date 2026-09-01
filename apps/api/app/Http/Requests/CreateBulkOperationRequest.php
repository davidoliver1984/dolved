<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BulkOperationType;
use App\Enums\BulkSelectionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CreateBulkOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operation_type' => ['required', Rule::enum(BulkOperationType::class)],
            'selection_mode' => ['required', Rule::enum(BulkSelectionMode::class)],
            'target_public_ids' => ['present', 'array'],
            'target_public_ids.*' => ['uuid', 'distinct'],
            'filters' => ['present', 'array:search,category,owner,applicability,review_status,searchable,status,historical,batch_public_id,preflight_status,match_status'],
            'filters.search' => ['sometimes', 'string', 'max:200'],
            'filters.category' => ['sometimes', 'uuid'],
            'filters.owner' => ['sometimes', 'uuid'],
            'filters.applicability' => ['sometimes', 'uuid'],
            'filters.review_status' => ['sometimes', Rule::in(['unassigned', 'overdue', 'due_soon'])],
            'filters.searchable' => ['sometimes', 'boolean'],
            'filters.status' => ['sometimes', 'string'],
            'filters.historical' => ['sometimes', 'boolean'],
            'filters.batch_public_id' => ['sometimes', 'uuid'],
            'filters.preflight_status' => ['sometimes', 'string'],
            'filters.match_status' => ['sometimes', 'string'],
            'payload' => ['present', 'array:location_public_ids,owner_user_public_id,category_public_id,mode,tag_public_ids,review_due_date'],
            'payload.location_public_ids' => ['sometimes', 'array', 'max:100'],
            'payload.location_public_ids.*' => ['uuid', 'distinct'],
            'payload.owner_user_public_id' => ['sometimes', 'uuid'],
            'payload.category_public_id' => ['sometimes', 'nullable', 'uuid'],
            'payload.mode' => ['sometimes', Rule::in(['add', 'remove', 'replace'])],
            'payload.tag_public_ids' => ['sometimes', 'array', 'max:20'],
            'payload.tag_public_ids.*' => ['uuid', 'distinct'],
            'payload.review_due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $mode = BulkSelectionMode::tryFrom((string) $this->input('selection_mode'));
            $targets = $this->input('target_public_ids', []);
            if ($mode === BulkSelectionMode::CurrentPage && (! is_array($targets) || $targets === [])) {
                $validator->errors()->add('target_public_ids', 'Current-page selection requires at least one target.');
            }
            if ($mode === BulkSelectionMode::AllFiltered && is_array($targets) && $targets !== []) {
                $validator->errors()->add('target_public_ids', 'All-filtered selection is resolved by the server and cannot accept browser-supplied targets.');
            }

            $type = BulkOperationType::tryFrom((string) $this->input('operation_type'));
            $payload = $this->input('payload', []);
            if (! is_array($payload) || $type === null) {
                return;
            }
            $allowed = match ($type) {
                BulkOperationType::Approval, BulkOperationType::Promotion => [],
                BulkOperationType::ApplicabilityChange => ['location_public_ids'],
                BulkOperationType::OwnerAssignment => ['owner_user_public_id'],
                BulkOperationType::CategoryAssignment => ['category_public_id'],
                BulkOperationType::TagChange => ['mode', 'tag_public_ids'],
                BulkOperationType::ReviewDateAssignment => ['review_due_date'],
            };
            foreach (array_diff(array_keys($payload), $allowed) as $key) {
                $validator->errors()->add("payload.{$key}", 'This field is not valid for the selected operation.');
            }
            $required = match ($type) {
                BulkOperationType::ApplicabilityChange => ['location_public_ids'],
                BulkOperationType::OwnerAssignment => ['owner_user_public_id'],
                BulkOperationType::TagChange => ['mode', 'tag_public_ids'],
                default => [],
            };
            foreach ($required as $key) {
                if (! array_key_exists($key, $payload)) {
                    $validator->errors()->add("payload.{$key}", 'This operation payload field is required.');
                }
            }
            if ($type === BulkOperationType::TagChange && isset($payload['mode'])
                && ! in_array($payload['mode'], ['add', 'remove', 'replace'], true)) {
                $validator->errors()->add('payload.mode', 'Tag mode must be add, remove, or replace.');
            }
        }];
    }
}
