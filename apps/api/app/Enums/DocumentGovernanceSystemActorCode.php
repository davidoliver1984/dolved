<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentGovernanceSystemActorCode: string
{
    case OwnerBackfillLineageRoot = 'owner_backfill_lineage_root';
    case OwnerBackfillWorkspaceCreatorFallback = 'owner_backfill_workspace_creator_fallback';
    case ChecksumBackfill = 'checksum_backfill';
    case AuditTargetScopeBackfill = 'audit_target_scope_backfill';
}
