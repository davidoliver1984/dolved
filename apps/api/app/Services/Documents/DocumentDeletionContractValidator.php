<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\InvalidIngestionEvent;
use JsonException;
use Opis\JsonSchema\Validator;
use RuntimeException;

class DocumentDeletionContractValidator
{
    private object $schema;

    public function __construct(private readonly Validator $validator)
    {
        $contents = file_get_contents((string) config('documents.deletion_contract_path'));
        if ($contents === false) {
            throw new RuntimeException('The document-deletion contract could not be read.');
        }
        try {
            $schema = json_decode($contents, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The document-deletion contract is invalid JSON.', previous: $exception);
        }
        if (! is_object($schema)) {
            throw new RuntimeException('The document-deletion contract must be an object.');
        }
        $this->schema = $schema;
    }

    /** @param array<string, mixed> $payload */
    public function validate(array $payload): void
    {
        $object = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        if (! $this->validator->validate($object, $this->schema)->isValid()) {
            throw new InvalidIngestionEvent('The deletion event is invalid.');
        }
    }
}
