<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Models\DocumentFamily;
use App\Models\Workspace;

final class CountReviewActionableWork
{
    /** @return array{due_soon: int, overdue: int} */
    public function handle(Workspace $workspace): array
    {
        $families = DocumentFamily::query()
            ->whereBelongsTo($workspace)
            ->whereNull('tombstoned_at')
            ->whereNotNull('review_due_date');

        return [
            'due_soon' => (clone $families)
                ->whereBetween('review_due_date', [today(), today()->addDays(30)])
                ->count(),
            'overdue' => (clone $families)
                ->whereDate('review_due_date', '<', today())
                ->count(),
        ];
    }
}
