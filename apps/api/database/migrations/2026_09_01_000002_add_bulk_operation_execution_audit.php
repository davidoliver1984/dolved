<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_operation_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('bulk_operation_id')->constrained()->restrictOnDelete();
            $table->foreignId('bulk_operation_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bulk_operation_item_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event_type');
            $table->foreignId('initiating_actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('executor_identity');
            $table->json('safe_context');
            $table->timestampTz('occurred_at');

            $table->index(['bulk_operation_id', 'occurred_at'], 'bulk_audit_operation_time');
            $table->index(['bulk_operation_item_id', 'occurred_at'], 'bulk_audit_item_time');
        });

        Schema::table('bulk_operation_items', function (Blueprint $table): void {
            $table->foreign('audit_event_id', 'bulk_operation_items_audit_event_foreign')
                ->references('id')->on('bulk_operation_audit_events')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bulk_operation_item_attempts DROP CONSTRAINT bulk_attempts_not_applied_reason_check');
            DB::statement("ALTER TABLE bulk_operation_item_attempts ADD CONSTRAINT bulk_attempts_not_applied_reason_check CHECK (not_applied_reason IS NULL OR not_applied_reason IN ('expected_state_mismatch','target_no_longer_exists','authorization_changed'))");
            DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_bulk_operation_audit_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'bulk operation audit events are immutable'; END; $$;
CREATE TRIGGER bulk_operation_audit_immutable_guard
BEFORE UPDATE OR DELETE ON bulk_operation_audit_events
FOR EACH ROW EXECUTE FUNCTION guard_bulk_operation_audit_immutable();

DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rag_platform_app') THEN
    REVOKE INSERT, UPDATE, DELETE ON bulk_operations, bulk_operation_items,
      bulk_operation_item_attempts, bulk_operation_item_subordinate_transitions,
      bulk_operation_audit_events FROM rag_platform_app;
    GRANT SELECT, INSERT ON bulk_operations TO rag_platform_app;
    GRANT UPDATE (status, membership_digest, confirmed_at, cancellation_requested_at, updated_at)
      ON bulk_operations TO rag_platform_app;
    GRANT SELECT ON bulk_operation_items, bulk_operation_item_attempts,
      bulk_operation_item_subordinate_transitions TO rag_platform_app;
    GRANT INSERT (bulk_operation_id, workspace_id, operation_type, ordinal,
      target_family_id, target_document_id, target_import_item_id, target_kind,
      target_public_id, target_display_label, expected_state_snapshot,
      eligibility_status, exclusion_reason, execution_status, terminal_reason,
      started_at, completed_at, subordinate_kind, subordinate_identity_kind,
      subordinate_identity_value, subordinate_awaited_since, result_identity,
      audit_event_id, incorporated_attempt_generation, created_at, updated_at)
      ON bulk_operation_items TO rag_platform_app;
    GRANT UPDATE (execution_status, terminal_reason, started_at, completed_at,
      subordinate_kind, subordinate_identity_kind, subordinate_identity_value,
      subordinate_awaited_since, result_identity, audit_event_id,
      incorporated_attempt_generation, updated_at) ON bulk_operation_items TO rag_platform_app;
    GRANT INSERT ON bulk_operation_item_attempts TO rag_platform_app;
    GRANT UPDATE (status, failure_category, not_applied_reason, completed_at,
      success_kind, result_digest, result_subordinate_kind, result_identity_kind,
      result_identity_value, updated_at) ON bulk_operation_item_attempts TO rag_platform_app;
    GRANT INSERT ON bulk_operation_item_subordinate_transitions TO rag_platform_app;
    GRANT SELECT, INSERT ON bulk_operation_audit_events TO rag_platform_app;
    GRANT USAGE, SELECT ON SEQUENCE bulk_operations_id_seq,
      bulk_operation_items_id_seq, bulk_operation_item_attempts_id_seq,
      bulk_operation_item_subordinate_transitions_id_seq,
      bulk_operation_audit_events_id_seq TO rag_platform_app;
  END IF;
END $$;
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS guard_bulk_operation_audit_immutable() CASCADE');
            DB::statement('ALTER TABLE bulk_operation_item_attempts DROP CONSTRAINT bulk_attempts_not_applied_reason_check');
            DB::statement("ALTER TABLE bulk_operation_item_attempts ADD CONSTRAINT bulk_attempts_not_applied_reason_check CHECK (not_applied_reason IS NULL OR not_applied_reason = 'expected_state_mismatch')");
        }
        Schema::table('bulk_operation_items', function (Blueprint $table): void {
            $table->dropForeign('bulk_operation_items_audit_event_foreign');
        });
        Schema::dropIfExists('bulk_operation_audit_events');
    }
};
