<?php
declare(strict_types=1);

// Rehearsal only: never registered by a service provider or a question worker.
function installEmptyQueueRehearsal(Illuminate\Contracts\Events\Dispatcher $events, Illuminate\Http\Client\Factory $http, ?string $receipts = null): void
{
    $record = static function (string $kind) use ($receipts): void {
        if ($receipts === null) { return; } // Controlled fixture only.
        if (is_link($receipts) || realpath($receipts) !== $receipts || !is_dir($receipts)) { throw new RuntimeException('rehearsal_receipt_boundary'); }
        $f=fopen($receipts.'/'.$kind.'.json','x');
        if ($f===false) { throw new RuntimeException('rehearsal_receipt_exists'); }
        try { $v=json_encode(['kind'=>$kind,'pid'=>getmypid()],JSON_THROW_ON_ERROR);if(fwrite($f,$v)!==strlen($v)||!fflush($f)||!fsync($f))throw new RuntimeException('rehearsal_receipt_write'); } finally { fclose($f); }
    };
    $http->preventStrayRequests();
    $blocked = false;
    $events->listen(Illuminate\Queue\Events\Looping::class, static function ($event) use (&$blocked, $record): bool {
        if ($event->connectionName !== 'conversation' || $event->queue !== 'conversation-generation') {
            throw new RuntimeException('rehearsal_queue_boundary');
        }
        // Worker::daemonShouldRun uses Dispatcher::until: false enters pauseWorker
        // before manager->connection/getNextJob. No pop/reservation occurs.
        if (!$blocked) { $record('poll_prevented'); $blocked=true; }
        return false;
    });
    $record('no_job_no_http_guard_installed');
}
