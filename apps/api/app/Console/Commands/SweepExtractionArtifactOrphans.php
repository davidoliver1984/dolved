<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Ingestion\SweepExtractionArtifactOrphans as SweepAction;
use Illuminate\Console\Command;

class SweepExtractionArtifactOrphans extends Command
{
    protected $signature = 'ingestion:extraction-artifacts:sweep {--limit=}';

    protected $description = 'Delete bounded, lease-inactive extraction artifact orphans.';

    public function handle(SweepAction $action): int
    {
        $limit = $this->option('limit');
        $result = $action->handle(is_numeric($limit) ? (int) $limit : null);
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
