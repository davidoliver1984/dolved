<?php

declare(strict_types=1);

namespace Tests\Unit;

use JsonException;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

final class WorkerHttpContractTest extends TestCase
{
    private const array EXPECTED_OPERATIONS = [
        'ingestion.claim',
        'ingestion.lease.renew',
        'ingestion.chunks.submit',
        'ingestion.chunks.seal',
        'ingestion.attempt.resume',
        'ingestion.publication.authorise',
        'ingestion.complete',
        'ingestion.fail',
        'ingestion.attempt.cancel',
        'document.deletion.claim',
        'document.deletion.complete',
        'document.deletion.fail',
    ];

    /** @throws JsonException */
    public function test_shared_worker_operation_vectors_validate_and_fail_closed(): void
    {
        $observed = [];

        foreach ($this->contractDirectories() as $directory) {
            $fixture = $this->load("{$directory}/worker-operation-vectors.json");
            $this->assertSame(1, $fixture['contract_version']);

            foreach ($fixture['operations'] as $operation) {
                $observed[] = $operation['purpose'];

                foreach (['request', 'response'] as $target) {
                    $this->assertMatchesSchema(
                        "{$directory}/{$operation[$target.'_schema']}",
                        $operation[$target],
                        "{$operation['name']} {$target}",
                    );
                }

                foreach ($operation['invalid'] as $mutation) {
                    $target = $mutation['target'];
                    $this->assertDoesNotMatchSchema(
                        "{$directory}/{$operation[$target.'_schema']}",
                        $this->mutate($operation[$target], $mutation),
                        "{$operation['name']} accepted {$mutation['case']}",
                    );
                }
            }
        }

        sort($observed);
        $expected = self::EXPECTED_OPERATIONS;
        sort($expected);
        $this->assertSame($expected, $observed);
    }

    /** @throws JsonException */
    public function test_claim_fixtures_also_match_the_authoritative_event_contracts(): void
    {
        $root = $this->repositoryRoot();
        $pairs = [
            [
                "{$root}/contracts/http/ingestion-worker/v1",
                "{$root}/contracts/events/document-ingestion-requested/v1.schema.json",
            ],
            [
                "{$root}/contracts/http/document-deletion-worker/v1",
                "{$root}/contracts/events/document-deletion-requested/v1.schema.json",
            ],
        ];

        foreach ($pairs as [$directory, $eventSchema]) {
            $fixture = $this->load("{$directory}/worker-operation-vectors.json");
            $operation = $fixture['operations'][0];
            $this->assertMatchesSchema($eventSchema, $operation['request'], 'claim event');
            foreach ($operation['invalid'] as $mutation) {
                if ($mutation['target'] === 'request') {
                    $this->assertDoesNotMatchSchema(
                        $eventSchema,
                        $this->mutate($operation['request'], $mutation),
                        "claim event accepted {$mutation['case']}",
                    );
                }
            }
        }
    }

    /** @return list<string> */
    private function contractDirectories(): array
    {
        $root = $this->repositoryRoot();

        return [
            "{$root}/contracts/http/ingestion-worker/v1",
            "{$root}/contracts/http/document-deletion-worker/v1",
        ];
    }

    private function repositoryRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /** @return array<string, mixed> @throws JsonException */
    private function load(string $path): array
    {
        $value = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($value);

        return $value;
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $mutation @return array<string, mixed> */
    private function mutate(array $payload, array $mutation): array
    {
        $path = $mutation['path'];
        $cursor = &$payload;
        foreach (array_slice($path, 0, -1) as $segment) {
            $cursor = &$cursor[$segment];
        }
        $last = $path[array_key_last($path)];
        if ($mutation['action'] === 'remove') {
            unset($cursor[$last]);
        } else {
            $cursor[$last] = $mutation['value'];
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function assertMatchesSchema(string $schemaPath, array $payload, string $message): void
    {
        $schema = json_decode((string) file_get_contents($schemaPath));
        $data = json_decode(json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertTrue((new Validator)->validate($data, $schema)->isValid(), $message);
    }

    /** @param array<string, mixed> $payload */
    private function assertDoesNotMatchSchema(string $schemaPath, array $payload, string $message): void
    {
        $schema = json_decode((string) file_get_contents($schemaPath));
        $data = json_decode(json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertFalse((new Validator)->validate($data, $schema)->isValid(), $message);
    }
}
