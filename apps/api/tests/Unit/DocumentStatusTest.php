<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DocumentStatus;
use PHPUnit\Framework\TestCase;

class DocumentStatusTest extends TestCase
{
    public function test_it_contains_exactly_the_accepted_lifecycle_states(): void
    {
        $this->assertSame(
            [
                'uploading',
                'uploaded',
                'queued',
                'processing',
                'indexed',
                'failed',
                'deleting',
                'deleted',
            ],
            array_column(DocumentStatus::cases(), 'value'),
        );
    }
}
