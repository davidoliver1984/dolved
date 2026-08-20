<?php

declare(strict_types=1);

namespace App\Contracts\Platform;

interface OperationalMetricsReader
{
    /** @return array<string, mixed> */
    public function snapshot(): array;
}
