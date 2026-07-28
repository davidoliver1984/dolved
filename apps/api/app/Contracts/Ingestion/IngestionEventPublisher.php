<?php

declare(strict_types=1);

namespace App\Contracts\Ingestion;

interface IngestionEventPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(array $payload): string;
}
