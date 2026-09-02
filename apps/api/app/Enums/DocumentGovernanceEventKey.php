<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentGovernanceEventKey: string
{
    case ImportBatchCompleted = 'import.batch.completed';
    case ImportBatchCompletedWithExceptions = 'import.batch.completed_with_exceptions';
    case ImportItemProcessingFailed = 'import.item.processing_failed';
    case ImportItemRequiresUserAction = 'import.item.requires_user_action';
    case ImportItemMatchAmbiguous = 'import.item.match_ambiguous';
    case GovernanceVersionApproved = 'governance.version.approved';
    case PromotionCompleted = 'promotion.completed';
    case PromotionFailed = 'promotion.failed';
    case GovernanceAuthorityApproaching = 'governance.authority.approaching';
    case GovernanceAuthorityAttained = 'governance.authority.attained';
    case GovernanceAuthorityBlocked = 'governance.authority.blocked';
    case GovernanceReviewDueSoon = 'governance.review.due_soon';
    case GovernanceReviewOverdue = 'governance.review.overdue';
    case GovernanceOwnershipReassignmentRequired = 'governance.ownership.reassignment_required';
    case ApplicabilitySuccessorCompleted = 'applicability.successor.completed';
    case ApplicabilitySuccessorFailed = 'applicability.successor.failed';
    case BulkOperationCompleted = 'bulk_operation.completed';
    case BulkOperationCompletedWithExceptions = 'bulk_operation.completed_with_exceptions';
    case BulkOperationFailedBeforeExecution = 'bulk_operation.failed_before_execution';
    case DeletionOperationStuckOrFailed = 'deletion.operation.stuck_or_failed';
}
