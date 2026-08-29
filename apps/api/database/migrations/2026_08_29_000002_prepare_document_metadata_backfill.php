<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE document_governance_audit_events DROP CONSTRAINT document_governance_audits_system_actor_code_check');
        DB::statement(<<<'SQL'
            ALTER TABLE document_governance_audit_events
            ADD CONSTRAINT document_governance_audits_system_actor_code_check
            CHECK (
                system_actor_code IS NULL
                OR system_actor_code IN (
                    'title_backfill',
                    'owner_backfill_lineage_root',
                    'owner_backfill_workspace_creator_fallback',
                    'checksum_backfill',
                    'audit_target_scope_backfill'
                )
            )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE document_families
            ADD CONSTRAINT document_families_owner_required_check
            CHECK (owner_user_id IS NOT NULL) NOT VALID
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE document_families DROP CONSTRAINT IF EXISTS document_families_owner_required_check');
        DB::statement('ALTER TABLE document_governance_audit_events DROP CONSTRAINT document_governance_audits_system_actor_code_check');
        DB::statement(<<<'SQL'
            ALTER TABLE document_governance_audit_events
            ADD CONSTRAINT document_governance_audits_system_actor_code_check
            CHECK (
                system_actor_code IS NULL
                OR system_actor_code IN (
                    'owner_backfill_lineage_root',
                    'owner_backfill_workspace_creator_fallback',
                    'checksum_backfill',
                    'audit_target_scope_backfill'
                )
            )
            SQL);
    }
};
