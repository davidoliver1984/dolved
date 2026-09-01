<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkTerminalReason: string
{
    case TargetNoLongerExists = 'target_no_longer_exists';
    case ExpectedStateMismatch = 'expected_state_mismatch';
    case GovernanceInputsChanged = 'governance_inputs_changed';
    case AuthorityWindowConflict = 'authority_window_conflict';
    case DecisionSnapshotChanged = 'decision_snapshot_changed';
    case StagingExpired = 'staging_expired';
    case AuthorizationChanged = 'authorization_changed';
    case PromotionConflict = 'promotion_conflict';
    case PromotionTechnicalFailure = 'promotion_technical_failure';
    case PromotionAbandonedExternally = 'promotion_abandoned_externally';
    case PromotionExpired = 'promotion_expired';
    case PredecessorStateChanged = 'predecessor_state_changed';
    case FullIngestionFailed = 'full_ingestion_failed';
    case MembershipChangedBeforeMutation = 'membership_changed_before_mutation';
    case RequestedTagSetChangedBeforeMutation = 'requested_tag_set_changed_before_mutation';
    case AuthorizationInsufficient = 'authorization_insufficient';
    case RetryCeilingExhausted = 'retry_ceiling_exhausted';
    case CancellationRequested = 'cancellation_requested';
}
