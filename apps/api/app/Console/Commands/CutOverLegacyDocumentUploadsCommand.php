<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Imports\CloseLegacyUploadInitializationGate;
use App\Actions\Imports\InventoryLegacyUploads;
use Illuminate\Console\Command;

final class CutOverLegacyDocumentUploadsCommand extends Command
{
    protected $signature = 'imports:cut-over-legacy-uploads {--batch-size=500} {--max-batches=100}';

    protected $description = 'Inventory legacy uploads in bounded batches and atomically close their initialization gate';

    public function handle(InventoryLegacyUploads $inventory, CloseLegacyUploadInitializationGate $close): int
    {
        $batchSize = max(1, (int) $this->option('batch-size'));
        $maxBatches = max(1, (int) $this->option('max-batches'));
        $marked = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $count = $inventory->handle($batchSize);
            $marked += $count;
            if ($count === 0) {
                break;
            }
        }
        if (! $close->handle($batchSize)) {
            $this->warn("Marked {$marked} documents; the bounded final remainder was too large. Re-run safely.");

            return self::FAILURE;
        }
        $this->info("Legacy upload initialization is closed; {$marked} inventory documents were marked in this run.");

        return self::SUCCESS;
    }
}
