<?php

use App\Actions\BulkOperations\ReclaimExpiredBulkAttempts;
use App\Actions\Conversation\ReconcileStaleGenerationRuns;
use App\Actions\Documents\DetectStuckOrFailedDocumentDeletions;
use App\Actions\Imports\ReconcileExpiredImportPreflights;
use App\Actions\Telemetry\RecordOperationalSnapshot;
use App\Actions\Workspaces\ExpireWorkspaceInvitations;
use App\Jobs\ExecuteBulkOperation;
use App\Models\BulkOperation;
use App\Models\ChatDeliveryEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('conversation:reconcile-stale-runs', function (ReconcileStaleGenerationRuns $reconcile): void {
    $this->info("Reconciled {$reconcile->handle()} stale conversation runs.");
})->purpose('Fail closed conversation runs abandoned by a hard worker failure');

Schedule::command('conversation:reconcile-stale-runs')->everyMinute()->withoutOverlapping();

Artisan::command('conversation:purge-expired-delivery-events', function (): void {
    $deleted = ChatDeliveryEvent::query()->where('expires_at', '<=', now())->delete();
    $this->info("Purged {$deleted} expired chat delivery events.");
})->purpose('Remove bounded-retention provisional and replay delivery data');

Schedule::command('conversation:purge-expired-delivery-events')->hourly()->withoutOverlapping();

Artisan::command('workspace-invitations:expire', function (ExpireWorkspaceInvitations $expire): void {
    $this->info("Expired {$expire->handle()} workspace invitations.");
})->purpose('Materialise expired workspace invitation state and audit its transition');

Schedule::command('workspace-invitations:expire')->everyMinute()->withoutOverlapping();

Artisan::command('observability:record-operational-snapshot', function (RecordOperationalSnapshot $record): void {
    $record->handle();
    $this->info('Recorded the bounded operational snapshot.');
})->purpose('Record bounded operational queue, dependency and stuck-operation gauges');

Schedule::command('observability:record-operational-snapshot')->everyMinute()->withoutOverlapping();

Schedule::command('ingestion:extraction-artifacts:sweep')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('ingestion:content-clone-manifests:sweep')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('documents:sweep-extraction-projections')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('imports:reconcile-expired-preflights', function (ReconcileExpiredImportPreflights $reconcile): void {
    $this->info("Reclaimed {$reconcile->handle()} expired import preflight attempts.");
})->purpose('Expire stale import preflight leases and dispatch their successor generation');

Schedule::command('imports:reconcile-expired-preflights')->everyMinute()->withoutOverlapping();

Artisan::command('bulk-operations:reconcile', function (ReclaimExpiredBulkAttempts $reclaim): void {
    $reclaimed = $reclaim->handle();
    $dispatched = 0;
    BulkOperation::query()->whereIn('status', ['queued', 'running'])->orderBy('id')->limit(100)
        ->each(function (BulkOperation $operation) use (&$dispatched): void {
            ExecuteBulkOperation::dispatch($operation->id);
            $dispatched++;
        });
    $this->info("Reclaimed {$reclaimed} expired attempts and dispatched {$dispatched} active bulk operations.");
})->purpose('Reclaim fenced attempts and resume bounded bulk-operation convergence');

Schedule::command('bulk-operations:reconcile')->everyMinute()->withoutOverlapping();

Artisan::command('documents:detect-stuck-or-failed-deletions', function (DetectStuckOrFailedDocumentDeletions $detect): void {
    $this->info("Recorded {$detect->handle()} new stuck or permanently failed deletion occurrences.");
})->purpose('Project durable governance events for visibly stuck or permanently failed document deletion operations');

Schedule::command('documents:detect-stuck-or-failed-deletions')->daily()->withoutOverlapping();
