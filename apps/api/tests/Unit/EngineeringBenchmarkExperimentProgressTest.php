<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Evaluation\EngineeringBenchmarkExperimentProgress;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class EngineeringBenchmarkExperimentProgressTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/rag-evaluation-progress-'.Str::uuid();
        config()->set('evaluation.runs_root', $this->root);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_progress_is_atomic_resumable_and_lineage_bound(): void
    {
        $progress = app(EngineeringBenchmarkExperimentProgress::class);
        $lineage = ['benchmark' => ['digest' => str_repeat('a', 64)], 'policy' => ['version' => 'v1']];
        $manifest = $progress->initialise('EXP-0001-test', $lineage);
        $progress->writeObservation(
            'EXP-0001-test',
            $manifest['lineage_digest'],
            'case.current',
            'direct',
            $this->observation('case.current', 'direct'),
        );

        $resumed = $progress->initialise('EXP-0001-test', $lineage);
        $completed = $progress->completedIdentities('EXP-0001-test', $resumed['lineage_digest']);

        $this->assertEquals($manifest, $resumed);
        $this->assertSame(['case.current::direct' => true], $completed);
        $this->expectException(RuntimeException::class);
        $progress->initialise('EXP-0001-test', ['benchmark' => ['digest' => str_repeat('b', 64)]]);
    }

    public function test_corrupted_observation_fails_closed(): void
    {
        $progress = app(EngineeringBenchmarkExperimentProgress::class);
        $manifest = $progress->initialise('EXP-0001-corrupt', ['benchmark' => ['version' => 'v2']]);
        $progress->writeObservation(
            'EXP-0001-corrupt',
            $manifest['lineage_digest'],
            'case.current',
            'direct',
            $this->observation('case.current', 'direct'),
        );
        $path = glob($this->root.'/EXP-0001-corrupt/observations/*.json')[0];
        file_put_contents($path, '{}');

        $this->expectException(RuntimeException::class);
        $progress->completedIdentities('EXP-0001-corrupt', $manifest['lineage_digest']);
    }

    public function test_completed_observation_cannot_be_replaced(): void
    {
        $progress = app(EngineeringBenchmarkExperimentProgress::class);
        $manifest = $progress->initialise('EXP-0001-immutable', ['benchmark' => ['version' => 'v2']]);
        $progress->writeObservation(
            'EXP-0001-immutable',
            $manifest['lineage_digest'],
            'case.current',
            'direct',
            $this->observation('case.current', 'direct'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be replaced');
        $progress->writeObservation(
            'EXP-0001-immutable',
            $manifest['lineage_digest'],
            'case.current',
            'direct',
            $this->observation('case.current', 'direct', 'failed'),
        );
    }

    public function test_partial_checkpoint_is_ignored_and_final_order_is_explicit(): void
    {
        $progress = app(EngineeringBenchmarkExperimentProgress::class);
        $manifest = $progress->initialise('EXP-0001-order', ['benchmark' => ['version' => 'v2']]);
        foreach ([['case.b', 'second'], ['case.a', 'first']] as [$caseId, $variantId]) {
            $progress->writeObservation(
                'EXP-0001-order',
                $manifest['lineage_digest'],
                $caseId,
                $variantId,
                $this->observation($caseId, $variantId),
            );
        }
        file_put_contents(
            $this->root.'/EXP-0001-order/observations/interrupted.json.tmp',
            '{"partial":',
        );

        $completed = $progress->completedIdentities(
            'EXP-0001-order',
            $manifest['lineage_digest'],
        );
        $path = $progress->finaliseFromCheckpoints(
            'EXP-0001-order',
            $manifest['lineage_digest'],
            [
                'schema_version' => 'v2',
                'run_id' => 'EXP-0001-order',
                'executed_at' => $manifest['started_at'],
            ],
            [
                ['case_id' => 'case.a', 'variant_id' => 'first'],
                ['case_id' => 'case.b', 'variant_id' => 'second'],
            ],
        );
        $payload = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertEqualsCanonicalizing(
            ['case.a::first', 'case.b::second'],
            array_keys($completed),
        );
        $this->assertSame(
            ['case.a', 'case.b'],
            array_map(
                fn (array $observation): string => $observation['case']['case_id'],
                $payload['observations'],
            ),
        );
    }

    public function test_checkpoint_discovery_fails_closed_for_wrong_lineage_and_duplicate_identity(): void
    {
        $progress = app(EngineeringBenchmarkExperimentProgress::class);
        $manifest = $progress->initialise('EXP-0001-duplicate', ['benchmark' => ['version' => 'v2']]);
        $progress->writeObservation(
            'EXP-0001-duplicate',
            $manifest['lineage_digest'],
            'case.current',
            'direct',
            $this->observation('case.current', 'direct'),
        );
        $original = glob($this->root.'/EXP-0001-duplicate/observations/*.json')[0];
        copy($original, $this->root.'/EXP-0001-duplicate/observations/'.str_repeat('f', 64).'.json');

        $this->expectException(RuntimeException::class);
        $progress->completedIdentities('EXP-0001-duplicate', $manifest['lineage_digest']);
    }

    public function test_large_checkpoint_execution_and_finalisation_remain_bounded(): void
    {
        $progress = app(EngineeringBenchmarkExperimentProgress::class);
        $manifest = $progress->initialise('EXP-0001-memory', ['benchmark' => ['version' => 'v2']]);
        $padding = str_repeat('x', 2_500_000);
        $identities = [];
        $baseline = memory_get_usage(true);
        $maximum = $baseline;
        for ($index = 0; $index < 126; $index++) {
            $caseId = sprintf('case.%03d', intdiv($index, 3));
            $variantId = sprintf('variant.%03d', $index);
            $identities[] = ['case_id' => $caseId, 'variant_id' => $variantId];
            $progress->writeObservation(
                'EXP-0001-memory',
                $manifest['lineage_digest'],
                $caseId,
                $variantId,
                $this->observation($caseId, $variantId) + [
                    'diagnostic_padding' => $padding,
                ],
            );
            $maximum = max($maximum, memory_get_usage(true));
        }
        $completed = $progress->completedIdentities(
            'EXP-0001-memory',
            $manifest['lineage_digest'],
        );
        $path = $progress->finaliseFromCheckpoints(
            'EXP-0001-memory',
            $manifest['lineage_digest'],
            [
                'schema_version' => 'v2',
                'run_id' => 'EXP-0001-memory',
                'executed_at' => $manifest['started_at'],
            ],
            $identities,
        );
        $maximum = max($maximum, memory_get_usage(true));

        $this->assertCount(126, $completed);
        $this->assertGreaterThan(300_000_000, filesize($path));
        $this->assertLessThan(32 * 1024 * 1024, $maximum - $baseline);
    }

    /** @return array<string, mixed> */
    private function observation(
        string $caseId,
        string $variantId,
        string $planningStatus = 'succeeded',
    ): array {
        $retrievalExecuted = $planningStatus === 'succeeded';

        return [
            'case' => ['case_id' => $caseId],
            'variant' => ['variant_id' => $variantId],
            'latency_ms' => 1.0,
            'observed_at' => '2026-08-12T12:00:00+00:00',
            'planning' => ['status' => $planningStatus],
            'retrieval_executed' => $retrievalExecuted,
            'dense' => $retrievalExecuted ? ['result' => [], 'trace' => []] : null,
            'hybrid' => $retrievalExecuted ? ['result' => [], 'trace' => []] : null,
        ];
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $child = $path.'/'.$entry;
            is_dir($child) ? $this->deleteDirectory($child) : @unlink($child);
        }
        @rmdir($path);
    }
}
