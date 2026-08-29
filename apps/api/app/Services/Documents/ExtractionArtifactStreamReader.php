<?php

declare(strict_types=1);

namespace App\Services\Documents;

use InvalidArgumentException;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

final class ExtractionArtifactStreamReader
{
    public function __construct(private readonly ExtractionArtifactObjectStorage $storage) {}

    /** @return iterable<int, array<string, mixed>> */
    public function elements(string $objectKey): iterable
    {
        return $this->objectsAt($objectKey, '/elements');
    }

    /** @return iterable<int, array<string, mixed>> */
    public function warnings(string $objectKey): iterable
    {
        return $this->objectsAt($objectKey, '/extraction_warnings');
    }

    /** @return array<string, mixed> */
    public function objectAt(string $objectKey, string $pointer): array
    {
        $value = [];
        foreach ($this->items($objectKey, $pointer) as $key => $item) {
            $value[(string) $key] = $item;
        }

        return $value;
    }

    /** @return array<int, array<string, mixed>> */
    public function arrayAt(string $objectKey, string $pointer): array
    {
        return iterator_to_array($this->objectsAt($objectKey, $pointer), false);
    }

    /** @return iterable<int, array<string, mixed>> */
    private function objectsAt(string $objectKey, string $pointer): iterable
    {
        foreach ($this->items($objectKey, $pointer) as $index => $value) {
            if (! is_array($value)) {
                throw new InvalidArgumentException("Extraction artifact {$pointer} entry {$index} is not an object.");
            }

            /** @var array<string, mixed> $value */
            yield (int) $index => $value;
        }
    }

    /** @return iterable<mixed, mixed> */
    private function items(string $objectKey, string $pointer): iterable
    {
        $stream = $this->storage->readStreamExact($objectKey);
        try {
            yield from Items::fromStream($stream, [
                'pointer' => $pointer,
                'decoder' => new ExtJsonDecoder(true),
            ]);
        } finally {
            fclose($stream);
        }
    }
}
