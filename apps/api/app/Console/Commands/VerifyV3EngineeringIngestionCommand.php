<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\VerifyV3EngineeringIngestion;
use Illuminate\Console\Command;
use Throwable;

final class VerifyV3EngineeringIngestionCommand extends Command
{
    protected $signature = 'evaluation:v3-engineering:verify-ingestion {--timeout=14400} {--poll-ms=1000}';

    protected $description = 'Wait for and verify normal V3 engineering ingestion and dense completeness';

    public function handle(VerifyV3EngineeringIngestion $verify): int
    {
        try {
            $state = $verify->handle((int) $this->option('timeout'), (int) $this->option('poll-ms'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info(sprintf('V3 dense ingestion verified with %d canonical chunks.', $state['canonical_chunk_count']));

        return self::SUCCESS;
    }
}
