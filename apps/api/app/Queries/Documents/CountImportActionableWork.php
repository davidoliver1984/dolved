<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\ImportMatchStatus;
use App\Enums\ImportPreflightStatus;
use App\Models\ImportItem;
use App\Models\Workspace;

final class CountImportActionableWork
{
    /** @return array{processing: int, warning: int} */
    public function handle(Workspace $workspace): array
    {
        $items = ImportItem::query()->whereBelongsTo($workspace);

        return [
            'processing' => (clone $items)->where(function ($query): void {
                $query->where('preflight_status', ImportPreflightStatus::Pending->value)
                    ->orWhere(function ($pendingMatch): void {
                        $pendingMatch->where('preflight_status', ImportPreflightStatus::Verified->value)
                            ->where('match_status', ImportMatchStatus::Pending->value);
                    });
            })->count(),
            'warning' => (clone $items)
                ->where('preflight_status', ImportPreflightStatus::Rejected->value)
                ->count(),
        ];
    }
}
