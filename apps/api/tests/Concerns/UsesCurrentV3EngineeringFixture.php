<?php

declare(strict_types=1);

namespace Tests\Concerns;

use RuntimeException;

trait UsesCurrentV3EngineeringFixture
{
    protected function setUp(): void
    {
        parent::setUp();

        $root = (string) env('V3_ENGINEERING_ROOT');
        $population = '/evaluation/engineering-populations/dolved-care-engineering/v3/v1';
        $benchmark = '/evaluation/benchmarks/dolved-care-engineering/v3';
        if (! is_dir($root.'/source') && ! mkdir($root.'/source', 0777, true) && ! is_dir($root.'/source')) {
            throw new RuntimeException('The V3 engineering test fixture root could not be created.');
        }

        $links = [
            'corpus.json' => $population.'/corpus.json',
            'expectations.json' => $population.'/expectations.json',
            'population-manifest.json' => $population.'/population-manifest.json',
            'independence.json' => $population.'/independence.json',
            'provisioning-definition.json' => $population.'/provisioning-definition.json',
            'organisation.json' => $benchmark.'/organisation.json',
            'document-catalog.json' => $benchmark.'/document-catalog.json',
            'source/documents' => $benchmark.'/documents',
        ];
        foreach ($links as $relative => $source) {
            $target = $root.'/'.$relative;
            if (is_link($target) && readlink($target) === $source) {
                continue;
            }
            if (file_exists($target) || is_link($target)) {
                throw new RuntimeException("Unexpected V3 engineering test fixture target: {$target}");
            }
            if (! symlink($source, $target)) {
                throw new RuntimeException("The V3 engineering test fixture link could not be created: {$target}");
            }
        }
    }
}
