<?php

declare(strict_types=1);

namespace App\Exceptions;

/** A completed reviewer selection rejected by the existing consumer checks. */
final class TopicalFamilySelectionRejected extends RetrievalException
{
    public function __construct(public readonly string $rejectionCode, public readonly array $usage,
        public readonly array $candidateIdentities)
    {
        parent::__construct('The comparison family review cited no authorised current evidence.');
    }
}
