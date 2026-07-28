<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Exceptions\InvalidIngestionEvent;
use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

class DocumentIngestionContractValidator
{
    private object $schema;

    public function __construct(
        private readonly Validator $validator,
    ) {
        $path = config('ingestion.contract_path');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException(
                'The ingestion contract path is not configured.',
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(
                'The canonical ingestion contract could not be read.',
            );
        }

        try {
            $schema = json_decode($contents, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'The canonical ingestion contract is not valid JSON.',
                previous: $exception,
            );
        }

        if (! is_object($schema)) {
            throw new RuntimeException(
                'The canonical ingestion contract must be a JSON object.',
            );
        }

        $this->schema = $schema;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload): void
    {
        $object = json_decode(
            json_encode($payload, JSON_THROW_ON_ERROR),
            flags: JSON_THROW_ON_ERROR,
        );
        $result = $this->validator->validate($object, $this->schema);

        if ($result->isValid()) {
            return;
        }

        $error = $result->error();
        $details = $error === null
            ? ['The payload did not match the canonical contract.']
            : (new ErrorFormatter)->formatFlat($error);

        throw new InvalidIngestionEvent(implode(' ', $details));
    }
}
