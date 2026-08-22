<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Evaluation\BuildCurrentRetrievalEligibilityArtifact;
use App\Support\Evaluation\BenchmarkCanonicalJson;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class ResolveCurrentRetrievalEligibilityCommand extends Command
{
    protected $signature = 'evaluation:resolve-current-eligibility
        {--run= : Immutable evaluation run identity}
        {--repository-commit= : Exact committed repository revision}
        {--document-catalog= : Independent document catalogue}
        {--organisation= : Independent organisation catalogue}
        {--plans= : Authored deterministic plans}
        {--schema= : Shared eligibility artifact schema}
        {--output= : Output artifact path}';

    protected $description = 'Resolve deterministic evaluation plans through the real Laravel eligibility boundary';

    public function handle(BuildCurrentRetrievalEligibilityArtifact $build, BenchmarkCanonicalJson $canonical): int
    {
        try {
            $this->assertEnvironment();
            $run = (string) $this->option('run');
            if (preg_match('/^[a-z0-9][a-z0-9-]{2,127}$/', $run) !== 1) {
                throw new RuntimeException('The evaluation run identity is invalid.');
            }
            $artifact = $build->handle(
                $run,
                (string) $this->option('repository-commit'),
                (string) $this->option('document-catalog'),
                (string) $this->option('organisation'),
                (string) $this->option('plans'),
                (string) $this->option('schema'),
            );
            $output = (string) $this->option('output');
            if ($output === '' || file_put_contents($output, $canonical->encode($artifact, true)) === false) {
                throw new RuntimeException('The eligibility artifact could not be written.');
            }
            $this->components->info('Real Laravel eligibility resolution completed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertEnvironment(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $databaseMarker = (string) config('e2e.database_marker');
        if (! app()->environment('e2e')
            || config('e2e.resource_marker') !== 'dolved-e2e'
            || getenv('EVALUATION_CURRENT_IDENTITY') !== 'dolved-evaluation-current'
            || $databaseMarker === ''
            || ! str_contains($database, $databaseMarker)) {
            throw new RuntimeException('Current eligibility evaluation is restricted to the isolated dolved-e2e identity.');
        }
    }
}
