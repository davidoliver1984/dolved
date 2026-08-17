<?php

declare(strict_types=1);

namespace App\Support\Generation;

final readonly class AnswerPartResult
{
    /** @param list<string> $evidenceIds */
    public function __construct(public string $text, public array $evidenceIds) {}
}
