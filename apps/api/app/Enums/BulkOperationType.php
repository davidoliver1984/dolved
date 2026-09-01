<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkOperationType: string
{
    case Approval = 'bulk_approval';
    case Promotion = 'bulk_promotion';
    case ApplicabilityChange = 'bulk_applicability_change';
    case OwnerAssignment = 'bulk_owner_assignment';
    case CategoryAssignment = 'bulk_category_assignment';
    case TagChange = 'bulk_tag_change';
    case ReviewDateAssignment = 'bulk_review_date_assignment';

    public function targetKind(): BulkTargetKind
    {
        return match ($this) {
            self::Approval => BulkTargetKind::Version,
            self::Promotion => BulkTargetKind::ImportItem,
            default => BulkTargetKind::Family,
        };
    }
}
