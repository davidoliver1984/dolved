<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\BulkOperations\ResolveBulkOperationTerminalState;
use App\Enums\BulkOperationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ResolveBulkOperationTerminalStateTest extends TestCase
{
    #[DataProvider('terminalCases')]
    public function test_normative_terminal_mapping_is_total(
        string $freeze,
        int $total,
        bool $cancelled,
        array $distribution,
        ?BulkOperationStatus $expected,
    ): void {
        $this->assertSame($expected, app(ResolveBulkOperationTerminalState::class)->handle(
            $freeze,
            $total,
            $cancelled,
            $distribution,
        ));
    }

    public static function terminalCases(): array
    {
        return [
            'freeze failure' => ['failed', 0, false, [], BulkOperationStatus::FailedBeforeExecution],
            'zero targets' => ['succeeded', 0, false, [], BulkOperationStatus::CompletedWithExclusions],
            'non terminal' => ['succeeded', 1, false, ['eligible' => 1], null],
            'cancelled before work' => ['succeeded', 2, true, ['cancelled' => 1, 'excluded' => 1], BulkOperationStatus::Cancelled],
            'cancelled after success' => ['succeeded', 2, true, ['succeeded' => 1, 'cancelled' => 1], BulkOperationStatus::CancelledAfterPartialExecution],
            'exception' => ['succeeded', 2, false, ['succeeded' => 1, 'skipped' => 1], BulkOperationStatus::CompletedWithExceptions],
            'defensive cancelled' => ['succeeded', 1, false, ['cancelled' => 1], BulkOperationStatus::CompletedWithExceptions],
            'exclusions' => ['succeeded', 2, false, ['succeeded' => 1, 'excluded' => 1], BulkOperationStatus::CompletedWithExclusions],
            'completed' => ['succeeded', 2, false, ['succeeded' => 2], BulkOperationStatus::Completed],
        ];
    }
}
