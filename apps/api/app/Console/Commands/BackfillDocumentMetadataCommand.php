<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Documents\BackfillDocumentMetadata;
use Illuminate\Console\Command;

final class BackfillDocumentMetadataCommand extends Command
{
    protected $signature = 'documents:backfill-metadata
        {--batch-size=100 : Maximum records processed per backfill lane}';

    protected $description = 'Backfill legacy document titles, owners, audit lineage and streamed checksums';

    public function handle(BackfillDocumentMetadata $backfill): int
    {
        $batchSize = filter_var($this->option('batch-size'), FILTER_VALIDATE_INT);

        if ($batchSize === false || $batchSize < 1 || $batchSize > 1000) {
            $this->components->error('The batch size must be an integer from 1 to 1000.');

            return self::FAILURE;
        }

        $summary = $backfill->handle($batchSize);
        $this->components->info(sprintf(
            'Owners %d; titles %d; audit lineages %d; checksums verified %d; unavailable %d; retryable %d; remaining %d.',
            $summary['owners'],
            $summary['titles'],
            $summary['audit_lineages'],
            $summary['checksums_verified'],
            $summary['checksums_unavailable'],
            $summary['checksums_retryable'],
            $summary['remaining'],
        ));

        return $summary['checksums_retryable'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
