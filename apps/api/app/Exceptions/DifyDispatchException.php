<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DifyDispatchException extends RuntimeException
{
    public function __construct(
        public readonly string $category,
        public readonly bool $dispatchUncertain,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct('The Dify request failed with a bounded operational error.');
    }
}
