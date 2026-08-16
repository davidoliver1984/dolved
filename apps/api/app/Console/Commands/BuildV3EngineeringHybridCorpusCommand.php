<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\BuildV3EngineeringHybridCorpus;
use Illuminate\Console\Command;
use Throwable;

final class BuildV3EngineeringHybridCorpusCommand extends Command
{
    protected $signature = 'evaluation:v3-engineering:build-hybrid {--batch-size=10}';

    protected $description = 'Build, verify and activate the V3 engineering hybrid corpus';

    public function handle(BuildV3EngineeringHybridCorpus $build): int
    {
        try {
            $state = $build->handle((int) $this->option('batch-size'));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $generation = $state['generations']['hybrid_corpus'];
        $this->components->info(sprintf(
            'V3 hybrid generation %s is MATERIALISED with %d points.',
            $generation['public_id'],
            $generation['actual_point_count'],
        ));

        return self::SUCCESS;
    }
}
