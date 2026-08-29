<?php

declare(strict_types=1);

namespace App\Services\Documents;

use InvalidArgumentException;
use LogicException;
use Ramsey\Uuid\Uuid;

final class StructuredExtractionCanonicaliser
{
    /** @param array<string, mixed> $artifact */
    public function canonicalBytes(array $artifact): string
    {
        return $this->canonicalize($this->normaliseValue($artifact));
    }

    /** @param array<string, mixed> $artifact */
    public function artifactDigest(array $artifact): string
    {
        return hash('sha256', $this->canonicalBytes($artifact));
    }

    /** @param array<string, mixed> $value */
    public function canonicalValueBytes(array $value): string
    {
        return $this->canonicalize($this->normaliseValue($value));
    }

    /** @param iterable<array<string, mixed>> $values */
    public function manifestDigest(iterable $values): string
    {
        $context = hash_init('sha256');
        hash_update($context, '[');
        $first = true;
        foreach ($values as $value) {
            if (! $first) {
                hash_update($context, ',');
            }
            hash_update($context, $this->canonicalValueBytes($value));
            $first = false;
        }
        hash_update($context, ']');

        return hash_final($context);
    }

    /** @param array<string, mixed> $artifact */
    public function projectionManifestDigest(array $artifact): string
    {
        return hash('sha256', $this->canonicalize($this->projectionManifest($artifact)));
    }

    /** @param array<string, mixed> $artifact */
    public function warningManifestDigest(array $artifact): string
    {
        return hash('sha256', $this->canonicalize($this->warningManifest($artifact)));
    }

    /**
     * @param  array<string, mixed>  $artifact
     * @return array<int, array<string, mixed>>
     */
    public function projectionManifest(array $artifact): array
    {
        if (! isset($artifact['elements']) || ! is_array($artifact['elements']) || ! array_is_list($artifact['elements'])) {
            throw new InvalidArgumentException('Artifact elements must be an array.');
        }

        $elements = array_map(function (mixed $element): array {
            if (! is_array($element)) {
                throw new InvalidArgumentException('Every artifact element must be an object.');
            }

            /** @var array<string, mixed> $normalised */
            $normalised = $this->normaliseValue($element);

            return $normalised;
        }, $artifact['elements']);

        usort($elements, static fn (array $left, array $right): int => [$left['ordinal'], $left['id']] <=> [$right['ordinal'], $right['id']]);

        return $elements;
    }

    /**
     * @param  array<string, mixed>  $artifact
     * @return array<int, array<string, mixed>>
     */
    public function warningManifest(array $artifact): array
    {
        if (! isset($artifact['extraction_warnings']) || ! is_array($artifact['extraction_warnings']) || ! array_is_list($artifact['extraction_warnings'])) {
            throw new InvalidArgumentException('Artifact extraction_warnings must be an array.');
        }

        return array_map(function (mixed $warning): array {
            if (! is_array($warning)) {
                throw new InvalidArgumentException('Every extraction warning must be an object.');
            }

            /** @var array<string, mixed> $normalised */
            $normalised = $this->normaliseValue($warning);

            return $normalised;
        }, $artifact['extraction_warnings']);
    }

    private function canonicalUuid(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Artifact contains an invalid UUID.');
        }

        try {
            return strtolower(Uuid::fromString($value)->toString());
        } catch (\Throwable $error) {
            throw new InvalidArgumentException('Artifact contains an invalid UUID.', previous: $error);
        }
    }

    private function canonicalize(mixed $value): string
    {
        if ($value === null || is_bool($value) || is_string($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_int($value)) {
            if (abs($value) > 9007199254740991) {
                throw new LogicException('Integer exceeds the RFC 8785 safe domain.');
            }

            return (string) $value;
        }

        if (is_float($value)) {
            return $this->canonicalFloat($value);
        }

        if (! is_array($value)) {
            throw new LogicException('Unsupported RFC 8785 value type.');
        }

        if (array_is_list($value)) {
            return '['.implode(',', array_map(fn (mixed $item): string => $this->canonicalize($item), $value)).']';
        }

        uksort($value, static fn (string $left, string $right): int => strcmp(
            mb_convert_encoding($left, 'UTF-16BE', 'UTF-8'),
            mb_convert_encoding($right, 'UTF-16BE', 'UTF-8'),
        ));

        $members = [];
        foreach ($value as $key => $item) {
            $members[] = json_encode((string) $key, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                .':'.$this->canonicalize($item);
        }

        return '{'.implode(',', $members).'}';
    }

    private function canonicalFloat(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            throw new LogicException('Non-finite numbers are not valid RFC 8785 values.');
        }
        if ($value === 0.0) {
            return '0';
        }

        $encoded = strtolower(json_encode($value, JSON_THROW_ON_ERROR));
        if (! str_contains($encoded, 'e')) {
            return $encoded;
        }

        [$mantissa, $exponentValue] = explode('e', $encoded, 2);
        $exponent = (int) $exponentValue;
        if (abs($value) >= 1e-6 && abs($value) < 1e21) {
            return $this->expandScientific($mantissa, $exponent);
        }

        $mantissa = rtrim(rtrim($mantissa, '0'), '.');
        $sign = $exponent >= 0 ? '+' : '-';

        return $mantissa.'e'.$sign.abs($exponent);
    }

    private function expandScientific(string $mantissa, int $exponent): string
    {
        $sign = str_starts_with($mantissa, '-') ? '-' : '';
        $unsigned = ltrim($mantissa, '-');
        $point = strpos($unsigned, '.');
        $digits = str_replace('.', '', $unsigned);
        $decimalPosition = ($point === false ? strlen($unsigned) : $point) + $exponent;

        if ($decimalPosition <= 0) {
            return rtrim($sign.'0.'.str_repeat('0', -$decimalPosition).$digits, '0');
        }
        if ($decimalPosition >= strlen($digits)) {
            return $sign.$digits.str_repeat('0', $decimalPosition - strlen($digits));
        }

        return rtrim($sign.substr($digits, 0, $decimalPosition).'.'.substr($digits, $decimalPosition), '0');
    }

    private function normaliseValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normaliseValue($item), $value);
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[(string) $key] = $this->normaliseValue($item);
        }

        if (array_key_exists('id', $result) && array_key_exists('kind', $result)) {
            $result['id'] = $this->canonicalUuid($result['id']);
        }
        if (array_key_exists('element_id', $result)) {
            $result['element_id'] = $this->canonicalUuid($result['element_id']);
        }
        if (array_key_exists('source_element_ids', $result)) {
            if (! is_array($result['source_element_ids'])) {
                throw new InvalidArgumentException('source_element_ids must be an array.');
            }
            $result['source_element_ids'] = array_map(
                fn (mixed $item): string => $this->canonicalUuid($item),
                $result['source_element_ids'],
            );
        }

        return $result;
    }
}
