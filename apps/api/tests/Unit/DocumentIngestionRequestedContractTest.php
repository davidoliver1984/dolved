<?php

declare(strict_types=1);

namespace Tests\Unit;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DocumentIngestionRequestedContractTest extends TestCase
{
    private const array INVALID_FIXTURES = [
        'invalid-missing-workspace-id.json' => 'required',
        'invalid-unknown-field.json' => 'additionalProperties',
        'invalid-unsupported-version.json' => 'const',
        'invalid-zero-byte-size.json' => 'minimum',
    ];

    private string $contractDirectory;

    private object $schema;

    private Validator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contractDirectory = self::findContractDirectory();
        $this->schema = $this->decodeObject(
            $this->contractDirectory.'/v1.schema.json',
        );
        $this->validator = new Validator;
    }

    public function test_valid_example_matches_the_canonical_v1_schema(): void
    {
        $payload = $this->decodeObject(
            $this->contractDirectory.'/v1.example.json',
        );

        $result = $this->validator->validate($payload, $this->schema);

        $this->assertTrue(
            $result->isValid(),
            $result->error()?->message() ?? 'Expected a valid payload.',
        );
    }

    #[DataProvider('invalidFixtureProvider')]
    public function test_invalid_fixture_fails_for_the_intended_keyword(
        string $fixture,
        string $expectedKeyword,
    ): void {
        $payload = $this->decodeObject(
            $this->contractDirectory.'/fixtures/'.$fixture,
        );

        $error = $this->validator->validate($payload, $this->schema)->error();

        $this->assertNotNull($error);
        $this->assertContains(
            $expectedKeyword,
            $this->leafKeywords($error),
            "The {$fixture} fixture did not fail on {$expectedKeyword}.",
        );
    }

    public function test_every_shared_invalid_fixture_has_an_expected_reason(): void
    {
        $fixtures = glob($this->contractDirectory.'/fixtures/*.json');

        if ($fixtures === false) {
            throw new RuntimeException('Unable to list contract fixtures.');
        }

        $fixtureNames = array_map('basename', $fixtures);
        sort($fixtureNames);

        $expectedNames = array_keys(self::INVALID_FIXTURES);
        sort($expectedNames);

        $this->assertSame($expectedNames, $fixtureNames);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidFixtureProvider(): array
    {
        $fixtures = [];

        foreach (self::INVALID_FIXTURES as $fixture => $expectedKeyword) {
            $fixtures[$fixture] = [$fixture, $expectedKeyword];
        }

        return $fixtures;
    }

    /**
     * @return list<string>
     */
    private function leafKeywords(ValidationError $error): array
    {
        return (new ErrorFormatter)->formatFlat(
            $error,
            static fn (ValidationError $validationError): string => $validationError->keyword(),
        );
    }

    private function decodeObject(string $path): object
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read JSON file: {$path}");
        }

        $decoded = json_decode($contents, flags: JSON_THROW_ON_ERROR);

        if (! is_object($decoded)) {
            throw new RuntimeException("Expected a JSON object: {$path}");
        }

        return $decoded;
    }

    private static function findContractDirectory(): string
    {
        $directory = __DIR__;

        while (true) {
            $candidate = $directory
                .'/contracts/events/document-ingestion-requested';

            if (is_dir($candidate)) {
                $resolved = realpath($candidate);

                if ($resolved !== false) {
                    return $resolved;
                }
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
        }

        throw new RuntimeException(
            'Unable to locate the canonical document-ingestion-requested contract directory.',
        );
    }
}
