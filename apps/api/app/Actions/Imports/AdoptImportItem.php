<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\PromotionAttemptStatus;
use App\Enums\PromotionOperationKind;
use App\Enums\WorkspaceRole;
use App\Exceptions\ImportPromotionException;
use App\Models\ImportItem;
use App\Models\PromotionAttempt;
use App\Models\User;

final readonly class AdoptImportItem
{
    public function __construct(
        private CreateImportDecisionSnapshot $snapshots,
        private ReserveImportPromotion $promotions,
    ) {}

    /** @param array<string, mixed> $revisedDefinition */
    public function handle(ImportItem $item, User $actor, array $revisedDefinition, string $idempotencyKey): PromotionAttempt
    {
        $authorised = $actor->workspaceMemberships()
            ->where('workspace_id', $item->workspace_id)
            ->whereIn('role', [WorkspaceRole::Owner->value, WorkspaceRole::Admin->value])
            ->exists();
        if (! $authorised) {
            throw ImportPromotionException::conflict('adoption_not_permitted');
        }
        $priorAttempt = $item->promotionAttempts()->orderByDesc('attempt_ordinal')->first();
        if ($priorAttempt === null
            || $priorAttempt->status !== PromotionAttemptStatus::Conflict
            || $priorAttempt->actor_user_id === $actor->id) {
            throw ImportPromotionException::conflict('adoption_not_permitted');
        }
        $current = $item->currentDecisionSnapshot;
        $snapshot = $this->snapshots->handle($item, $actor, $revisedDefinition);
        if ($current !== null && $snapshot->id === $current->id) {
            throw ImportPromotionException::conflict('adoption_requires_new_decision');
        }

        return $this->promotions->handle($item->refresh(), $actor, PromotionOperationKind::Adopt, $idempotencyKey);
    }
}
