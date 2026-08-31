<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportPreflightAttemptStatus;
use App\Enums\ImportPreflightStatus;
use App\Exceptions\ImportPreflightException;
use App\Models\ImportPreflightAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReconcileExpiredImportPreflights
{
    public function __construct(private readonly StartImportPreflight $start) {}

    public function handle(): int
    {
        $ids = ImportPreflightAttempt::query()
            ->where('status', ImportPreflightAttemptStatus::Open->value)
            ->where('lease_expires_at', '<=', now())
            ->orderBy('id')
            ->limit((int) config('imports.preflight.reclaim_batch_size'))
            ->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $attempt = ImportPreflightAttempt::query()->with('item')->lockForUpdate()->find($id);
                if ($attempt === null || $attempt->status !== ImportPreflightAttemptStatus::Open || $attempt->lease_expires_at->isFuture()) {
                    return;
                }
                $attempt->forceFill([
                    'status' => ImportPreflightAttemptStatus::Expired,
                    'diagnostic_code' => 'lease_expired',
                    'completed_at' => now(),
                ])->save();
            });
        }

        $terminalAttempts = ImportPreflightAttempt::query()
            ->with('item')
            ->where('status', ImportPreflightAttemptStatus::Expired->value)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('import_preflight_attempts as newer')
                    ->whereColumn('newer.import_item_id', 'import_preflight_attempts.import_item_id')
                    ->whereColumn('newer.lease_generation', '>', 'import_preflight_attempts.lease_generation');
            })
            ->orderBy('id')
            ->limit((int) config('imports.preflight.reclaim_batch_size'))
            ->get();

        $reclaimed = 0;
        foreach ($terminalAttempts as $attempt) {
            $item = $attempt->item;
            if ($item->preflight_status !== ImportPreflightStatus::Pending) {
                continue;
            }
            try {
                $this->start->handle($item);
                $reclaimed++;
            } catch (ImportPreflightException $exception) {
                if (! in_array($exception->reason, ['preflight_already_open', 'preflight_not_eligible'], true)) {
                    Log::warning('Import preflight successor dispatch remains pending.', [
                        'import_item_id' => $item->public_id,
                        'diagnostic_code' => 'successor_dispatch_unavailable',
                    ]);
                }
            } catch (Throwable) {
                Log::warning('Import preflight successor dispatch remains pending.', [
                    'import_item_id' => $item->public_id,
                    'diagnostic_code' => 'successor_dispatch_unavailable',
                ]);
            }
        }

        return $reclaimed;
    }
}
