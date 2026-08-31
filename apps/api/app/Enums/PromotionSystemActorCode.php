<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionSystemActorCode: string
{
    case PromotionReconciler = 'promotion_reconciler';
    case RetentionSweep = 'retention_sweep';
    case LegacyDrainReconciler = 'legacy_drain_reconciler';
}
