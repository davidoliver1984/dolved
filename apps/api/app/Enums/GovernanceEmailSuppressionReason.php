<?php

declare(strict_types=1);

namespace App\Enums;

enum GovernanceEmailSuppressionReason: string
{
    case WorkspaceEmailDisabled = 'workspace_email_disabled';
    case PersonalOptOut = 'personal_opt_out';
    case RecipientDisabled = 'recipient_disabled';
    case RecipientUnverified = 'recipient_unverified';
    case MembershipRemoved = 'membership_removed';
    case AuthorityLost = 'authority_lost';
    case NoDeliverableMembers = 'no_deliverable_members';
}
