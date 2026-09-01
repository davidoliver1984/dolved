<?php

declare(strict_types=1);

namespace App\Enums;

enum BulkExclusionReason: string
{
    case NotIndexed = 'not_indexed';
    case AlreadyApprovedOrCurrent = 'already_approved_or_current';
    case Withdrawn = 'withdrawn';
    case AuthorizationInsufficient = 'authorization_insufficient';
    case PreflightNotVerified = 'preflight_not_verified';
    case MatchUnresolved = 'match_unresolved';
    case ReadinessCriteriaIncomplete = 'readiness_criteria_incomplete';
    case NoAuthoritativePredecessor = 'no_authoritative_predecessor';
    case NoOpUnchangedApplicability = 'no_op_unchanged_applicability';
    case InvalidOrRetiredLocation = 'invalid_or_retired_location';
    case RequestedOwnerNotActiveMember = 'requested_owner_not_active_member';
    case CurrentOwnerAlreadyMatches = 'current_owner_already_matches';
    case CategoryArchivedOrDeleted = 'category_archived_or_deleted';
    case AlreadyAssigned = 'already_assigned';
    case AddRemoveReplaceNoOp = 'add_remove_replace_no_op';
    case TagLimitExceeded = 'tag_limit_exceeded';
    case InvalidDate = 'invalid_date';
    case SameExistingDate = 'same_existing_date';
}
