<?php

declare(strict_types=1);

namespace App\Exceptions;

/** A bounded diagnostic for the shadow reviewer; never an eligibility decision. */
final class LocationReferenceReviewDiagnosticException extends RetrievalException
{
    public function __construct(
        public readonly string $failureReason,
        public readonly ?string $rawResponse = null,
        public readonly bool $rawResponseTruncated = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct('The location-reference shadow review failed.', 0, $previous);
    }
}
