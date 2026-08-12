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
            ['planning' => ['status' => 'succeeded']],
        );

        $resumed = $progress->initialise('EXP-0001-test', $lineage);
        $observations = $progress->observations('EXP-0001-test', $resumed['lineage_digest']);

        $this->assertEquals($manifest, $resumed);
        $this->assertSame(
            ['planning' => ['status' => 'succeeded']],
            $observations['case.current::direct'],
        );
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
            ['planning' => ['status' => 'succeeded']],
        );
        $path = glob($this->root.'/EXP-0001-corrupt/observations/*.json')[0];
        file_put_contents($path, '{}');

        $this->expectException(RuntimeException::class);
        $progress->observations('EXP-0001-corrupt', $manifest['lineage_digest']);
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
