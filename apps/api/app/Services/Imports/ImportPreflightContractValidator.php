<?php

declare(strict_types=1);

namespace App\Services\Imports;

use App\Exceptions\InvalidIngestionEvent;
use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

final class ImportPreflightContractValidator
{
    public function __construct(private readonly Validator $validator) {}

    /** @param array<string, mixed> $payload */
    public function validateDispatch(array $payload): void
    {
        $this->validate($payload, (string) config('imports.preflight.contract_path'));
    }

    /** @param array<string, mixed> $payload */
    public function validateComplete(array $payload): void
    {
        $this->validate($payload, (string) config('imports.preflight.complete_contract_path'));
    }

    /** @param array<string, mixed> $payload */
    public function validateFail(array $payload): void
    {
        $this->validate($payload, (string) config('imports.preflight.fail_contract_path'));
    }

    /** @param array<string, mixed> $payload */
    private function validate(array $payload, string $path): void
    {
        $contents = $path === '' ? false : file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The import preflight contract could not be read.');
        }
        try {
            $schema = json_decode($contents, flags: JSON_THROW_ON_ERROR);
            $object = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The import preflight contract is not valid JSON.', previous: $exception);
        }
        $result = $this->validator->validate($object, $schema);
        if ($result->isValid()) {
            return;
        }
        $error = $result->error();
        $details = $error === null ? ['Contract validation failed.'] : (new ErrorFormatter)->formatFlat($error);
        throw new InvalidIngestionEvent(implode(' ', $details));
    }
}
