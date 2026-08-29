<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class DocumentGovernanceIdempotencyConflict extends DomainException {}
