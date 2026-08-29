<?php

use App\Actions\Conversation\ReconcileStaleGenerationRuns;
use App\Actions\Telemetry\RecordOperationalSnapshot;
use App\Actions\Workspaces\ExpireWorkspaceInvitations;
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
Schedule::command('documents:sweep-extraction-projections')->everyFiveMinutes()->withoutOverlapping();
