<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Conversation\OrchestrateConversationRun;
use App\Actions\Evaluation\RunR28ProductionPathDiagnostic;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class RunR28ProductionPathDiagnosticCommand extends Command
{
    protected $signature = 'evaluation:r28:production-path
        {--input= : Absolute question-only execution-input JSON path}
        {--selection= : Absolute frozen diagnostic selection JSON path}
        {--output= : New bounded aggregate observation JSON path}
        {--workspace= : Primary workspace public ID}
        {--user= : Authorised primary user ID}
        {--injection-workspace= : Injection-pack workspace public ID}
        {--injection-user= : Authorised injection-pack user ID}';

    protected $description = 'Execute the bounded R28 diagnostic through production conversation services';

    public function handle(RunR28ProductionPathDiagnostic $run): int
    {
        try {
            $inputPath = $this->absoluteFile((string) $this->option('input'));
            $selectionPath = $this->absoluteFile((string) $this->option('selection'));
            $outputPath = (string) $this->option('output');
            if ($outputPath === '' || file_exists($outputPath)) {
                throw new RuntimeException('The R28 output path must be new and explicit.');
            }
            $input = json_decode((string) file_get_contents($inputPath), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                throw new RuntimeException('The R28 execution input must be a JSON object.');
            }
            $run->validateInput($input);
            $selection = json_decode((string) file_get_contents($selectionPath), true, flags: JSON_THROW_ON_ERROR);
            $injectionIdentities = $selection['selection']['prompt_injection'] ?? null;
            if (! is_array($injectionIdentities) || count($injectionIdentities) !== 2) {
                throw new RuntimeException('The frozen R28 prompt-injection selection is invalid.');
            }
            $selectedIdentities = collect($selection['selection'] ?? null)
                ->flatMap(fn (mixed $identities): array => is_array($identities) ? array_values($identities) : [])
                ->values()
                ->all();
            $inputIdentities = collect($input['items'])
                ->map(fn (array $item): string => $item['case_id'].'::'.$item['variant_id'])
                ->values()
                ->all();
            sort($selectedIdentities);
            sort($inputIdentities);
            if ($selectedIdentities !== $inputIdentities) {
                throw new RuntimeException('The frozen R28 selection does not match the question-only execution input.');
            }
            if (isset($selection['execution_scope'])) {
                $primaryIdentities = $selection['execution_scope']['primary'] ?? null;
                $scopedInjectionIdentities = $selection['execution_scope']['injection'] ?? null;
                if (! is_array($primaryIdentities) || ! is_array($scopedInjectionIdentities)
                    || $scopedInjectionIdentities !== $injectionIdentities
                    || array_intersect($primaryIdentities, $scopedInjectionIdentities) !== []
                    || array_diff($inputIdentities, [...$primaryIdentities, ...$scopedInjectionIdentities]) !== []) {
                    throw new RuntimeException('The frozen R28 execution-scope mapping is inconsistent.');
                }
            }
            $primary = [
                'scope' => 'primary',
                'workspace' => (string) $this->option('workspace'),
                'user' => User::query()->whereKey((int) $this->option('user'))->sole(),
            ];
            $injection = [
                'scope' => 'injection',
                'workspace' => (string) $this->option('injection-workspace'),
                'user' => User::query()->whereKey((int) $this->option('injection-user'))->sole(),
            ];
            if ($primary['workspace'] === '' || $injection['workspace'] === '' || $primary['workspace'] === $injection['workspace']) {
                throw new RuntimeException('Primary and injection diagnostic workspaces must be explicit and distinct.');
            }
            $parent = dirname($outputPath);
            if (! is_dir($parent) || ! is_writable($parent)) {
                throw new RuntimeException('The R28 output directory must already exist and be writable.');
            }
            $caseDirectory = $outputPath.'.cases';
            if (! is_dir($caseDirectory) && ! mkdir($caseDirectory, 0700)) {
                throw new RuntimeException('The R28 case-record directory could not be created.');
            }
            $summaries = [];
            foreach ($input['items'] as $item) {
                $identity = $item['case_id'].'::'.$item['variant_id'];
                $binding = in_array($identity, $injectionIdentities, true) ? $injection : $primary;
                $casePath = $caseDirectory.'/'.preg_replace('/[^A-Za-z0-9._-]/', '_', $identity).'.json';
                if (is_file($casePath)) {
                    $record = json_decode((string) file_get_contents($casePath), true, flags: JSON_THROW_ON_ERROR);
                    if (($record['case_id'] ?? null) !== $item['case_id']
                        || ($record['variant_id'] ?? null) !== $item['variant_id']
                        || ($record['scope_classification'] ?? null) !== $binding['scope']
                        || ($record['workspace_id'] ?? null) !== $binding['workspace']) {
                        throw new RuntimeException('An existing R28 case record has incompatible identity or scope.');
                    }
                } else {
                    $record = $run->handleCase($binding['user'], $binding['workspace'], $item, $binding['scope']);
                    $this->writeNewJson($casePath, $record);
                }
                $summaries[] = $this->summary($casePath, $record);
                unset($record);
            }
            $result = [
                'schema_version' => 'r28-production-path-observation-index-v2',
                'subset_id' => $input['subset_id'],
                'population_id' => $input['population_id'],
                'population_digest' => $input['population_digest'],
                'execution_boundary' => OrchestrateConversationRun::class,
                'case_record_directory' => $caseDirectory,
                'case_count' => count($summaries),
                'items' => $summaries,
            ];
            $temporary = $outputPath.'.partial';
            if (file_exists($temporary)) {
                throw new RuntimeException('The R28 temporary output path already exists.');
            }
            file_put_contents($temporary, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
            if (! rename($temporary, $outputPath)) {
                throw new RuntimeException('The R28 observation could not be finalised atomically.');
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info('Captured '.count($result['items']).' bounded production-path observations at '.$outputPath.'.');

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $record */
    private function writeNewJson(string $path, array $record): void
    {
        $temporary = $path.'.partial';
        if (file_exists($path) || file_exists($temporary)) {
            throw new RuntimeException('The R28 case record path is not new.');
        }
        file_put_contents($temporary, json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
        if (! rename($temporary, $path)) {
            throw new RuntimeException('The R28 case record could not be finalised atomically.');
        }
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function summary(string $path, array $record): array
    {
        return [
            'case_id' => $record['case_id'],
            'variant_id' => $record['variant_id'],
            'run_id' => $record['run_id'],
            'scope_classification' => $record['scope_classification'],
            'workspace_id' => $record['workspace_id'],
            'status' => $record['status'],
            'failure_code' => $record['failure_code'],
            'retrieval_outcome' => $record['retrieval']['outcome'] ?? null,
            'evidence_count' => $record['retrieval']['evidence_count'] ?? 0,
            'case_record' => basename($path),
            'bytes' => filesize($path),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    private function absoluteFile(string $path): string
    {
        if ($path === '' || ! str_starts_with($path, '/') || ! is_file($path)) {
            throw new RuntimeException('The R28 input must be an existing absolute file path.');
        }

        return $path;
    }
}
