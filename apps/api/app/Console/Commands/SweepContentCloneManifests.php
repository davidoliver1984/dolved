<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Ingestion\SweepContentCloneManifests as SweepAction;
use Illuminate\Console\Command;

final class SweepContentCloneManifests extends Command
{
    protected $signature = 'ingestion:content-clone-manifests:sweep {--limit=}';

    protected $description = 'Delete bounded, lease-inactive content-clone manifests.';

    public function handle(SweepAction $action): int
    {
        $limit = $this->option('limit');
        $result = $action->handle(is_numeric($limit) ? (int) $limit : null);
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
