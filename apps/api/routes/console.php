<?php

use App\Actions\Conversation\ReconcileStaleGenerationRuns;
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
