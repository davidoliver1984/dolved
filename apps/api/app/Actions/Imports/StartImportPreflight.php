<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportPreflightAttemptStatus;
use App\Enums\ImportPreflightStatus;
use App\Exceptions\ImportPreflightException;
use App\Models\ImportItem;
use App\Models\ImportPreflightAttempt;
use App\Models\OutboxEvent;
use App\Services\Documents\ImportStagingStorage;
use App\Services\Imports\ImportPreflightContractValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StartImportPreflight
{
    public function __construct(
        private readonly ImportStagingStorage $storage,
        private readonly ImportPreflightContractValidator $contracts,
    ) {}

    public function handle(ImportItem $item, ?string $correlationId = null): ImportPreflightAttempt
    {
        $item->loadMissing(['batch', 'workspace']);
        if ($item->preflight_status !== ImportPreflightStatus::Pending
            || $item->batch->status === ImportBatchStatus::Expired
            || ! is_string($item->declared_media_type)
            || $item->declared_media_type === '') {
            throw ImportPreflightException::conflict('preflight_not_eligible');
        }
        $size = $this->storage->exactSize($item->workspace, $item);
        if ($size < 1 || $size > (int) config('documents.max_upload_bytes')) {
            $reason = $size < 1 ? 'empty_source' : 'size_limit_exceeded';
            DB::transaction(function () use ($item, $reason): void {
                $locked = ImportItem::query()->lockForUpdate()->findOrFail($item->id);
                if ($locked->preflight_status === ImportPreflightStatus::Pending) {
                    $locked->forceFill([
                        'preflight_status' => ImportPreflightStatus::Rejected,
                        'preflight_rejection_reason' => $reason,
                    ])->save();
                }
            });
            throw ImportPreflightException::invalid($reason);
        }
        $source = $this->storage->createPreflightReadRequest($item->workspace, $item);
        $token = (string) Str::uuid();
        $eventId = (string) Str::uuid();
        $correlationId ??= (string) Str::uuid();

        return DB::transaction(function () use ($item, $source, $token, $eventId, $correlationId): ImportPreflightAttempt {
            $locked = ImportItem::query()->with(['batch', 'workspace'])->lockForUpdate()->findOrFail($item->id);
            if ($locked->preflight_status !== ImportPreflightStatus::Pending || $locked->batch->status === ImportBatchStatus::Expired) {
                throw ImportPreflightException::conflict('preflight_not_eligible');
            }
            if (ImportPreflightAttempt::query()->where('import_item_id', $locked->id)->where('status', ImportPreflightAttemptStatus::Open->value)->exists()) {
                throw ImportPreflightException::conflict('preflight_already_open');
            }
            $generation = ((int) ImportPreflightAttempt::query()->where('import_item_id', $locked->id)->max('lease_generation')) + 1;
            $now = CarbonImmutable::now();
            $attempt = ImportPreflightAttempt::query()->create([
                'event_id' => $eventId,
                'import_item_id' => $locked->id,
                'workspace_id' => $locked->workspace_id,
                'lease_generation' => $generation,
                'lease_token_hash' => hash('sha256', $token),
                'lease_expires_at' => $now->addSeconds((int) config('imports.preflight.lease_seconds')),
                'staged_object_key' => $locked->staged_object_key,
                'declared_media_type' => $locked->declared_media_type,
                'status' => ImportPreflightAttemptStatus::Open,
            ]);
            $payload = [
                'event_id' => $eventId,
                'event_type' => 'import.preflight.requested',
                'event_version' => 1,
                'contract_version' => 'import-preflight-v1',
                'occurred_at' => $now->toIso8601String(),
                'workspace_id' => $locked->workspace->public_id,
                'import_batch_id' => $locked->batch->public_id,
                'import_item_id' => $locked->public_id,
                'staged_object' => $source,
                'declared_media_type' => $locked->declared_media_type,
                'lease_token' => $token,
                'lease_generation' => $generation,
                'correlation_id' => $correlationId,
            ];
            $this->contracts->validateDispatch($payload);
            OutboxEvent::query()->create([
                'event_id' => $eventId,
                'event_type' => 'import.preflight.requested',
                'event_version' => 1,
                'workspace_public_id' => $locked->workspace->public_id,
                'document_public_id' => null,
                'import_item_public_id' => $locked->public_id,
                'correlation_id' => $correlationId,
                'payload' => $payload,
                'occurred_at' => $now,
            ]);

            return $attempt;
        });
    }
}
