<?php

declare(strict_types=1);

namespace App\Services\Documents;

final readonly class SingleByteRange
{
    private function __construct(
        public int $start,
        public int $end,
    ) {}

    public static function parse(?string $header, int $length): ?self
    {
        if ($header === null) {
            return null;
        }

        if ($length === 0 || str_contains($header, ',')) {
            throw new UnsatisfiableByteRange;
        }

        if (preg_match('/^bytes=(\d+)-(\d+)$/D', $header, $match) === 1) {
            $start = self::integer($match[1]);
            $requestedEnd = self::integer($match[2]);
            if ($start > $requestedEnd || $start >= $length) {
                throw new UnsatisfiableByteRange;
            }

            return new self($start, min($requestedEnd, $length - 1));
        }

        if (preg_match('/^bytes=(\d+)-$/D', $header, $match) === 1) {
            $start = self::integer($match[1]);
            if ($start >= $length) {
                throw new UnsatisfiableByteRange;
            }

            return new self($start, $length - 1);
        }

        if (preg_match('/^bytes=-(\d+)$/D', $header, $match) === 1) {
            $suffix = self::integer($match[1]);
            if ($suffix === 0) {
                throw new UnsatisfiableByteRange;
            }

            return new self(max(0, $length - $suffix), $length - 1);
        }

        throw new UnsatisfiableByteRange;
    }

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    private static function integer(string $value): int
    {
        if (strlen($value) > 18) {
            throw new UnsatisfiableByteRange;
        }

        return (int) $value;
    }
}
