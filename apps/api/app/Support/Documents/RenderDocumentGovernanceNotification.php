<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentGovernanceEventKey;
use App\Models\DocumentGovernanceNotification;

final class RenderDocumentGovernanceNotification
{
    /** @return array{title: string, message: string} */
    public function handle(DocumentGovernanceNotification $notification): array
    {
        $label = $notification->target_display_label;

        return match ($notification->event_key) {
            DocumentGovernanceEventKey::ImportBatchCompleted => $this->copy('Import complete', 'Your document import finished.', $label),
            DocumentGovernanceEventKey::ImportBatchCompletedWithExceptions => $this->copy('Import needs attention', 'Your import finished with items that need attention.', $label),
            DocumentGovernanceEventKey::ImportItemProcessingFailed => $this->copy('Document could not be processed', 'Review the staged document and choose what to do next.', $label),
            DocumentGovernanceEventKey::ImportItemRequiresUserAction => $this->copy('Document needs your input', 'Review the staged document before it can continue.', $label),
            DocumentGovernanceEventKey::ImportItemMatchAmbiguous => $this->copy('Document match needs review', 'Choose the correct existing document family or create a new one.', $label),
            DocumentGovernanceEventKey::GovernanceVersionApproved => $this->copy('Document version approved', 'The document version was approved.', $label),
            DocumentGovernanceEventKey::PromotionCompleted => $this->copy('Document added to the library', 'The staged document was promoted successfully.', $label),
            DocumentGovernanceEventKey::PromotionFailed => $this->copy('Document promotion failed', 'The staged document could not be promoted.', $label),
            DocumentGovernanceEventKey::GovernanceReviewDueSoon => $this->copy('Review due soon', 'This document family is approaching its review date.', $label),
            DocumentGovernanceEventKey::GovernanceReviewOverdue => $this->copy('Review overdue', 'This document family has passed its review date.', $label),
            DocumentGovernanceEventKey::GovernanceOwnershipReassignmentRequired => $this->copy('Document owner required', 'Assign an eligible owner to this document family.', $label),
            DocumentGovernanceEventKey::GovernanceAuthorityBlocked => $this->copy('Scheduled authority blocked', 'A scheduled document version cannot attain authority.', $label),
            DocumentGovernanceEventKey::GovernanceAuthorityApproaching => $this->copy('Scheduled change approaching', 'A document version is approaching its authority date.', $label),
            DocumentGovernanceEventKey::GovernanceAuthorityAttained => $this->copy('Scheduled change active', 'A document version has attained authority.', $label),
            DocumentGovernanceEventKey::ApplicabilitySuccessorCompleted => $this->copy('Applicability update complete', 'The successor document was prepared successfully.', $label),
            DocumentGovernanceEventKey::ApplicabilitySuccessorFailed => $this->copy('Applicability update failed', 'The successor document could not be prepared.', $label),
            DocumentGovernanceEventKey::BulkOperationCompleted => $this->copy('Bulk operation complete', 'The bulk document operation completed.', $label),
            DocumentGovernanceEventKey::BulkOperationCompletedWithExceptions => $this->copy('Bulk operation completed with exceptions', 'Review the items that could not be changed.', $label),
            DocumentGovernanceEventKey::BulkOperationFailedBeforeExecution => $this->copy('Bulk operation did not start', 'The bulk document operation failed before execution.', $label),
            DocumentGovernanceEventKey::DeletionOperationStuckOrFailed => $this->copy('Document deletion needs attention', 'The deletion operation did not complete normally.', $label),
        };
    }

    /** @return array{title: string, message: string} */
    private function copy(string $title, string $message, ?string $label): array
    {
        return ['title' => $title, 'message' => $label ? "{$message} {$label}" : $message];
    }
}
