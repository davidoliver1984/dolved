<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Imports\ReconcileLegacyUploadDrain;
use Illuminate\Console\Command;

final class ReconcileLegacyDocumentUploadDrainCommand extends Command
{
    protected $signature = 'imports:reconcile-legacy-upload-drain {--limit=500}';

    protected $description = 'Expire stalled marked legacy uploads and close continuation routes when the browser-upload drain is empty';

    public function handle(ReconcileLegacyUploadDrain $reconcile): int
    {
        $result = $reconcile->handle(max(1, (int) $this->option('limit')));
        $this->info(sprintf(
            'Expired %d; remaining %d; drain %s.',
            $result['expired'],
            $result['remaining'],
            $result['drain_closed'] ? 'closed' : 'open',
        ));

        return self::SUCCESS;
    }
}
