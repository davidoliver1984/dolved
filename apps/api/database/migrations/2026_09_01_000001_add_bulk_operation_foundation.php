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
        Schema::create('bulk_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('actor_type');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('system_actor_code')->nullable();
            $table->string('actor_identity');
            $table->string('operation_type');
            $table->string('status')->default('preparing_membership');
            $table->text('canonical_payload');
            $table->unsignedInteger('payload_schema_version')->default(1);
            $table->string('selection_mode');
            $table->text('filter_explanation');
            $table->string('client_idempotency_key', 128);
            $table->char('request_digest', 64);
            $table->char('membership_digest', 64)->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancellation_requested_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'workspace_id', 'operation_type'], 'bulk_operations_parent_identity_unique');
            $table->unique(
                ['workspace_id', 'actor_identity', 'operation_type', 'client_idempotency_key'],
                'bulk_operations_idempotency_unique',
            );
            $table->index(['workspace_id', 'status', 'created_at'], 'bulk_operations_scope_status_created');
        });

        Schema::create('bulk_operation_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bulk_operation_id');
            $table->unsignedBigInteger('workspace_id');
            $table->string('operation_type');
            $table->unsignedInteger('ordinal');
            $table->foreignId('target_family_id')->nullable()->constrained('document_families')->nullOnDelete();
            $table->foreignId('target_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('target_import_item_id')->nullable()->constrained('import_items')->nullOnDelete();
            $table->string('target_reference_status')->default('live');
            $table->string('target_kind');
            $table->uuid('target_public_id');
            $table->string('target_display_label', 255);
            $table->json('expected_state_snapshot');
            $table->string('eligibility_status');
            $table->string('exclusion_reason')->nullable();
            $table->string('execution_status');
            $table->string('terminal_reason')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('subordinate_kind')->nullable();
            $table->string('subordinate_identity_kind')->nullable();
            $table->string('subordinate_identity_value')->nullable();
            $table->timestampTz('subordinate_awaited_since')->nullable();
            $table->string('result_identity')->nullable();
            $table->unsignedBigInteger('audit_event_id')->nullable();
            $table->unsignedInteger('incorporated_attempt_generation')->nullable();
            $table->timestampsTz();

            $table->foreign(
                ['bulk_operation_id', 'workspace_id', 'operation_type'],
                'bulk_operation_items_parent_foreign',
            )->references(['id', 'workspace_id', 'operation_type'])->on('bulk_operations')->restrictOnDelete();
            $table->unique(['id', 'workspace_id'], 'bulk_operation_items_id_workspace_unique');
            $table->unique(['bulk_operation_id', 'ordinal'], 'bulk_operation_items_parent_ordinal_unique');
            $table->index(['bulk_operation_id', 'execution_status'], 'bulk_operation_items_parent_execution');
        });

        Schema::create('bulk_operation_item_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bulk_operation_item_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedInteger('attempt_ordinal');
            $table->unsignedInteger('generation');
            $table->string('status')->default('open');
            $table->timestampTz('lease_expires_at');
            $table->string('failure_category')->nullable();
            $table->string('not_applied_reason')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->string('executor_identity');
            $table->string('invocation_idempotency_key', 191);
            $table->uuid('attempt_token');
            $table->string('success_kind')->nullable();
            $table->char('result_digest', 64)->nullable();
            $table->string('result_subordinate_kind')->nullable();
            $table->string('result_identity_kind')->nullable();
            $table->string('result_identity_value')->nullable();
            $table->timestampsTz();

            $table->foreign(
                ['bulk_operation_item_id', 'workspace_id'],
                'bulk_operation_item_attempts_item_workspace_foreign',
            )->references(['id', 'workspace_id'])->on('bulk_operation_items')->restrictOnDelete();
            $table->unique(['bulk_operation_item_id', 'attempt_ordinal'], 'bulk_attempts_item_ordinal_unique');
            $table->unique(['bulk_operation_item_id', 'generation'], 'bulk_attempts_item_generation_unique');
            $table->unique(['bulk_operation_item_id', 'invocation_idempotency_key'], 'bulk_attempts_invocation_unique');
        });

        Schema::table('bulk_operation_items', function (Blueprint $table): void {
            $table->foreign(
                ['id', 'incorporated_attempt_generation'],
                'bulk_operation_items_incorporated_attempt_foreign',
            )->references(['bulk_operation_item_id', 'generation'])
                ->on('bulk_operation_item_attempts')->restrictOnDelete();
        });

        Schema::create('bulk_operation_item_subordinate_transitions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bulk_operation_item_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedInteger('ordinal');
            $table->char('transition_key', 64);
            $table->string('subordinate_kind');
            $table->string('subordinate_identity_kind');
            $table->string('subordinate_identity_value');
            $table->string('transition_category');
            $table->timestampTz('recorded_at');
            $table->string('correlation_identity');
            $table->char('mapped_state_digest', 64)->nullable();

            $table->foreign(
                ['bulk_operation_item_id', 'workspace_id'],
                'bulk_subordinate_transitions_item_workspace_foreign',
            )->references(['id', 'workspace_id'])->on('bulk_operation_items')->restrictOnDelete();
            $table->unique(['bulk_operation_item_id', 'ordinal'], 'bulk_subordinate_transitions_item_ordinal_unique');
            $table->unique(['bulk_operation_item_id', 'transition_key'], 'bulk_subordinate_transitions_item_key_unique');
        });

        DB::statement('CREATE UNIQUE INDEX bulk_operation_items_family_target_unique ON bulk_operation_items (bulk_operation_id, target_family_id) WHERE target_family_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX bulk_operation_items_document_target_unique ON bulk_operation_items (bulk_operation_id, target_document_id) WHERE target_document_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX bulk_operation_items_import_target_unique ON bulk_operation_items (bulk_operation_id, target_import_item_id) WHERE target_import_item_id IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX bulk_operation_item_attempts_one_open_unique ON bulk_operation_item_attempts (bulk_operation_item_id) WHERE status = 'open'");

        if (DB::getDriverName() === 'pgsql') {
            $this->addPostgresConstraints();
            $this->addPostgresTriggers();
            $this->reconcileRuntimePrivileges();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS guard_bulk_subordinate_transition_immutable() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_bulk_attempt_update() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_bulk_operation_item_incorporation() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_bulk_operation_item_update() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_bulk_operation_item_target_workspace() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_bulk_operation_item_target_retirement() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS retire_bulk_operation_item_targets() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_bulk_operation_update() CASCADE');
        }

        Schema::dropIfExists('bulk_operation_item_subordinate_transitions');
        Schema::table('bulk_operation_items', function (Blueprint $table): void {
            $table->dropForeign('bulk_operation_items_incorporated_attempt_foreign');
        });
        Schema::dropIfExists('bulk_operation_item_attempts');
        Schema::dropIfExists('bulk_operation_items');
        Schema::dropIfExists('bulk_operations');
    }

    private function addPostgresConstraints(): void
    {
        DB::statement("ALTER TABLE bulk_operations ADD CONSTRAINT bulk_operations_actor_check CHECK ((actor_type = 'human' AND actor_user_id IS NOT NULL AND system_actor_code IS NULL AND actor_identity = 'user:' || actor_user_id::text) OR (actor_type = 'system' AND actor_user_id IS NULL AND system_actor_code IS NOT NULL AND actor_identity = 'system:' || system_actor_code))");
        DB::statement("ALTER TABLE bulk_operations ADD CONSTRAINT bulk_operations_type_check CHECK (operation_type IN ('bulk_approval','bulk_promotion','bulk_applicability_change','bulk_owner_assignment','bulk_category_assignment','bulk_tag_change','bulk_review_date_assignment'))");
        DB::statement("ALTER TABLE bulk_operations ADD CONSTRAINT bulk_operations_status_check CHECK (status IN ('preparing_membership','awaiting_confirmation','queued','running','completed','completed_with_exclusions','completed_with_exceptions','cancelled','cancelled_after_partial_execution','failed_before_execution'))");
        DB::statement("ALTER TABLE bulk_operations ADD CONSTRAINT bulk_operations_selection_check CHECK (selection_mode IN ('current_page','all_filtered'))");
        DB::statement("ALTER TABLE bulk_operations ADD CONSTRAINT bulk_operations_digest_check CHECK (request_digest ~ '^[0-9a-f]{64}$' AND (membership_digest IS NULL OR membership_digest ~ '^[0-9a-f]{64}$') AND payload_schema_version > 0)");

        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_reference_check CHECK (target_reference_status IN ('live','target_deleted'))");
        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_target_kind_check CHECK (target_kind IN ('family','version','import_item'))");
        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_target_shape_check CHECK ((target_reference_status = 'live' AND ((operation_type = 'bulk_approval' AND target_kind = 'version' AND target_document_id IS NOT NULL AND target_family_id IS NULL AND target_import_item_id IS NULL) OR (operation_type = 'bulk_promotion' AND target_kind = 'import_item' AND target_import_item_id IS NOT NULL AND target_family_id IS NULL AND target_document_id IS NULL) OR (operation_type IN ('bulk_applicability_change','bulk_owner_assignment','bulk_category_assignment','bulk_tag_change','bulk_review_date_assignment') AND target_kind = 'family' AND target_family_id IS NOT NULL AND target_document_id IS NULL AND target_import_item_id IS NULL))) OR (target_reference_status = 'target_deleted' AND target_family_id IS NULL AND target_document_id IS NULL AND target_import_item_id IS NULL))");
        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_eligibility_check CHECK (eligibility_status IN ('eligible','excluded'))");
        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_exclusion_reason_check CHECK (exclusion_reason IS NULL OR exclusion_reason IN ('not_indexed','already_approved_or_current','withdrawn','authorization_insufficient','preflight_not_verified','match_unresolved','readiness_criteria_incomplete','no_authoritative_predecessor','no_op_unchanged_applicability','invalid_or_retired_location','requested_owner_not_active_member','current_owner_already_matches','category_archived_or_deleted','already_assigned','add_remove_replace_no_op','tag_limit_exceeded','invalid_date','same_existing_date'))");
        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_execution_check CHECK (execution_status IN ('excluded','eligible','failed_retryable','waiting_on_subordinate','succeeded','failed_permanent','skipped','cancelled'))");
        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_terminal_reason_check CHECK (((execution_status IN ('skipped','failed_permanent','cancelled')) AND terminal_reason IN ('target_no_longer_exists','expected_state_mismatch','governance_inputs_changed','authority_window_conflict','decision_snapshot_changed','staging_expired','authorization_changed','promotion_conflict','promotion_technical_failure','promotion_abandoned_externally','promotion_expired','predecessor_state_changed','full_ingestion_failed','membership_changed_before_mutation','requested_tag_set_changed_before_mutation','authorization_insufficient','retry_ceiling_exhausted','cancellation_requested')) OR (execution_status NOT IN ('skipped','failed_permanent','cancelled') AND terminal_reason IS NULL))");
        DB::statement(<<<'SQL'
ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_truth_check CHECK (
  (execution_status = 'excluded' AND eligibility_status = 'excluded' AND exclusion_reason IS NOT NULL
    AND started_at IS NULL AND completed_at IS NULL AND audit_event_id IS NULL AND terminal_reason IS NULL
    AND result_identity IS NULL AND incorporated_attempt_generation IS NULL AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL)
  OR (execution_status = 'eligible' AND eligibility_status = 'eligible' AND exclusion_reason IS NULL
    AND started_at IS NULL AND completed_at IS NULL AND audit_event_id IS NULL AND terminal_reason IS NULL
    AND result_identity IS NULL AND incorporated_attempt_generation IS NULL AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL)
  OR (execution_status = 'failed_retryable' AND eligibility_status = 'eligible' AND exclusion_reason IS NULL
    AND started_at IS NOT NULL AND completed_at IS NULL AND audit_event_id IS NULL AND terminal_reason IS NULL
    AND result_identity IS NULL AND incorporated_attempt_generation IS NOT NULL AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL)
  OR (execution_status = 'waiting_on_subordinate' AND eligibility_status = 'eligible' AND exclusion_reason IS NULL
    AND started_at IS NOT NULL AND completed_at IS NULL AND audit_event_id IS NULL AND terminal_reason IS NULL
    AND result_identity IS NULL AND incorporated_attempt_generation IS NOT NULL AND subordinate_kind IS NOT NULL
    AND subordinate_identity_kind IS NOT NULL AND subordinate_identity_value IS NOT NULL AND subordinate_awaited_since IS NOT NULL)
  OR (execution_status = 'succeeded' AND eligibility_status = 'eligible' AND exclusion_reason IS NULL
    AND started_at IS NOT NULL AND completed_at IS NOT NULL AND audit_event_id IS NOT NULL AND terminal_reason IS NULL
    AND incorporated_attempt_generation IS NOT NULL AND ((subordinate_kind IS NULL AND subordinate_identity_kind IS NULL
      AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL) OR (subordinate_kind IS NOT NULL
      AND subordinate_identity_kind IS NOT NULL AND subordinate_identity_value IS NOT NULL AND subordinate_awaited_since IS NOT NULL)))
  OR (execution_status = 'failed_permanent' AND eligibility_status = 'eligible' AND exclusion_reason IS NULL
    AND started_at IS NOT NULL AND completed_at IS NOT NULL AND audit_event_id IS NOT NULL AND terminal_reason IS NOT NULL
    AND result_identity IS NULL AND incorporated_attempt_generation IS NOT NULL AND ((subordinate_kind IS NULL
      AND subordinate_identity_kind IS NULL AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL)
      OR (subordinate_kind IS NOT NULL AND subordinate_identity_kind IS NOT NULL AND subordinate_identity_value IS NOT NULL
      AND subordinate_awaited_since IS NOT NULL)))
  OR (execution_status = 'skipped' AND eligibility_status = 'eligible' AND exclusion_reason IS NULL
    AND ((started_at IS NULL AND incorporated_attempt_generation IS NULL) OR (started_at IS NOT NULL
      AND incorporated_attempt_generation IS NOT NULL)) AND completed_at IS NOT NULL AND audit_event_id IS NOT NULL
    AND terminal_reason IS NOT NULL AND result_identity IS NULL AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL)
  OR (execution_status = 'cancelled' AND eligibility_status = 'eligible' AND exclusion_reason IS NULL
    AND started_at IS NULL AND completed_at IS NOT NULL AND audit_event_id IS NOT NULL AND terminal_reason IS NOT NULL
    AND result_identity IS NULL AND incorporated_attempt_generation IS NULL AND subordinate_kind IS NULL
    AND subordinate_identity_kind IS NULL AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL)
)
SQL);
        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_result_shape_check CHECK (execution_status <> 'succeeded' OR ((operation_type IN ('bulk_promotion','bulk_applicability_change') AND result_identity IS NOT NULL) OR (operation_type IN ('bulk_approval','bulk_owner_assignment','bulk_category_assignment','bulk_tag_change','bulk_review_date_assignment') AND result_identity IS NULL)))");
        DB::statement("ALTER TABLE bulk_operation_items ADD CONSTRAINT bulk_operation_items_subordinate_kind_check CHECK ((subordinate_kind IN ('promotion_attempt','content_clone_operation') AND subordinate_identity_kind = 'public_id') OR (subordinate_kind = 'full_ingestion_fallback' AND subordinate_identity_kind = 'event_id') OR (subordinate_kind IS NULL AND subordinate_identity_kind IS NULL AND subordinate_identity_value IS NULL AND subordinate_awaited_since IS NULL))");

        DB::statement("ALTER TABLE bulk_operation_item_attempts ADD CONSTRAINT bulk_attempts_status_check CHECK (status IN ('open','succeeded','not_applied','failed_retryable','failed_permanent','abandoned'))");
        DB::statement("ALTER TABLE bulk_operation_item_attempts ADD CONSTRAINT bulk_attempts_not_applied_reason_check CHECK (not_applied_reason IS NULL OR not_applied_reason = 'expected_state_mismatch')");
        DB::statement("ALTER TABLE bulk_operation_item_attempts ADD CONSTRAINT bulk_attempts_shape_check CHECK ((status = 'open' AND completed_at IS NULL AND failure_category IS NULL AND not_applied_reason IS NULL AND success_kind IS NULL AND result_digest IS NULL AND result_subordinate_kind IS NULL AND result_identity_kind IS NULL AND result_identity_value IS NULL) OR (status = 'not_applied' AND completed_at IS NOT NULL AND failure_category IS NULL AND not_applied_reason IS NOT NULL AND success_kind IS NULL AND result_digest IS NULL AND result_subordinate_kind IS NULL AND result_identity_kind IS NULL AND result_identity_value IS NULL) OR (status IN ('failed_retryable','failed_permanent','abandoned') AND completed_at IS NOT NULL AND failure_category IS NOT NULL AND not_applied_reason IS NULL AND success_kind IS NULL AND result_digest IS NULL AND result_subordinate_kind IS NULL AND result_identity_kind IS NULL AND result_identity_value IS NULL) OR (status = 'succeeded' AND success_kind = 'database_local' AND completed_at IS NOT NULL AND failure_category IS NULL AND not_applied_reason IS NULL AND result_digest ~ '^[0-9a-f]{64}$' AND result_subordinate_kind IS NULL AND result_identity_kind IS NULL AND result_identity_value IS NULL) OR (status = 'succeeded' AND success_kind = 'subordinate_initiated' AND completed_at IS NOT NULL AND failure_category IS NULL AND not_applied_reason IS NULL AND result_digest ~ '^[0-9a-f]{64}$' AND result_subordinate_kind IS NOT NULL AND result_identity_kind IS NOT NULL AND result_identity_value IS NOT NULL))");
        DB::statement("ALTER TABLE bulk_operation_item_attempts ADD CONSTRAINT bulk_attempts_subordinate_shape_check CHECK ((result_subordinate_kind IN ('promotion_attempt','content_clone_operation') AND result_identity_kind = 'public_id') OR (result_subordinate_kind = 'full_ingestion_fallback' AND result_identity_kind = 'event_id') OR (result_subordinate_kind IS NULL AND result_identity_kind IS NULL AND result_identity_value IS NULL))");
        DB::statement('ALTER TABLE bulk_operation_item_attempts ADD CONSTRAINT bulk_attempts_ordinals_check CHECK (attempt_ordinal > 0 AND generation > 0)');
        DB::statement("ALTER TABLE bulk_operation_item_subordinate_transitions ADD CONSTRAINT bulk_subordinate_transition_digest_check CHECK (transition_key ~ '^[0-9a-f]{64}$' AND (mapped_state_digest IS NULL OR mapped_state_digest ~ '^[0-9a-f]{64}$'))");
        DB::statement("ALTER TABLE bulk_operation_item_subordinate_transitions ADD CONSTRAINT bulk_subordinate_transition_shape_check CHECK ((subordinate_kind IN ('promotion_attempt','content_clone_operation') AND subordinate_identity_kind = 'public_id') OR (subordinate_kind = 'full_ingestion_fallback' AND subordinate_identity_kind = 'event_id'))");
        DB::statement("ALTER TABLE bulk_operation_item_subordinate_transitions ADD CONSTRAINT bulk_subordinate_transition_category_check CHECK (transition_category IN ('initiated','adopted','fallback_started'))");
    }

    private function addPostgresTriggers(): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_bulk_operation_update() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.public_id <> OLD.public_id OR NEW.workspace_id <> OLD.workspace_id
     OR NEW.actor_type <> OLD.actor_type OR NEW.actor_user_id IS DISTINCT FROM OLD.actor_user_id
     OR NEW.system_actor_code IS DISTINCT FROM OLD.system_actor_code OR NEW.actor_identity <> OLD.actor_identity
     OR NEW.operation_type <> OLD.operation_type OR NEW.client_idempotency_key <> OLD.client_idempotency_key
     OR NEW.request_digest <> OLD.request_digest THEN
    RAISE EXCEPTION 'bulk operation identity is immutable';
  END IF;
  IF OLD.confirmed_at IS NOT NULL AND (NEW.canonical_payload <> OLD.canonical_payload
     OR NEW.selection_mode <> OLD.selection_mode OR NEW.filter_explanation <> OLD.filter_explanation
     OR NEW.payload_schema_version <> OLD.payload_schema_version
     OR NEW.membership_digest IS DISTINCT FROM OLD.membership_digest) THEN
    RAISE EXCEPTION 'confirmed bulk operation scope is immutable';
  END IF;
  RETURN NEW;
END; $$;
CREATE TRIGGER bulk_operations_update_guard BEFORE UPDATE ON bulk_operations
FOR EACH ROW EXECUTE FUNCTION guard_bulk_operation_update();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_bulk_operation_item_target_retirement() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE owner_name name;
BEGIN
  SELECT pg_get_userbyid(relowner) INTO owner_name FROM pg_class WHERE oid = 'bulk_operation_items'::regclass;
  IF TG_OP = 'INSERT' AND NEW.target_reference_status <> 'live' AND current_user <> owner_name THEN
    RAISE EXCEPTION 'only owner authority may insert retired bulk targets';
  END IF;
  IF TG_OP = 'UPDATE' AND NEW.target_reference_status IS DISTINCT FROM OLD.target_reference_status
     AND current_user <> owner_name THEN
    RAISE EXCEPTION 'only target retirement may change reference status';
  END IF;
  RETURN NEW;
END; $$;
CREATE TRIGGER bulk_operation_items_retirement_guard
BEFORE INSERT OR UPDATE ON bulk_operation_items FOR EACH ROW
EXECUTE FUNCTION guard_bulk_operation_item_target_retirement();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION enforce_bulk_operation_item_target_workspace() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE target_workspace bigint;
BEGIN
  IF NEW.target_family_id IS NOT NULL THEN SELECT workspace_id INTO target_workspace FROM document_families WHERE id = NEW.target_family_id;
  ELSIF NEW.target_document_id IS NOT NULL THEN SELECT workspace_id INTO target_workspace FROM documents WHERE id = NEW.target_document_id;
  ELSIF NEW.target_import_item_id IS NOT NULL THEN SELECT workspace_id INTO target_workspace FROM import_items WHERE id = NEW.target_import_item_id;
  ELSE RETURN NEW;
  END IF;
  IF target_workspace IS NULL OR target_workspace <> NEW.workspace_id THEN RAISE EXCEPTION 'bulk target workspace mismatch'; END IF;
  RETURN NEW;
END; $$;
CREATE TRIGGER bulk_operation_items_target_workspace_guard
BEFORE INSERT OR UPDATE OF target_family_id, target_document_id, target_import_item_id, workspace_id
ON bulk_operation_items FOR EACH ROW EXECUTE FUNCTION enforce_bulk_operation_item_target_workspace();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION retire_bulk_operation_item_targets() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, public AS $$
BEGIN
  IF TG_TABLE_NAME = 'document_families' THEN
    UPDATE public.bulk_operation_items SET target_family_id = NULL, target_reference_status = 'target_deleted'
      WHERE target_family_id = OLD.id AND target_reference_status = 'live';
  ELSIF TG_TABLE_NAME = 'documents' THEN
    UPDATE public.bulk_operation_items SET target_document_id = NULL, target_reference_status = 'target_deleted'
      WHERE target_document_id = OLD.id AND target_reference_status = 'live';
  ELSE
    UPDATE public.bulk_operation_items SET target_import_item_id = NULL, target_reference_status = 'target_deleted'
      WHERE target_import_item_id = OLD.id AND target_reference_status = 'live';
  END IF;
  RETURN OLD;
END; $$;
CREATE TRIGGER document_families_retire_bulk_targets BEFORE DELETE ON document_families
FOR EACH ROW EXECUTE FUNCTION retire_bulk_operation_item_targets();
CREATE TRIGGER documents_retire_bulk_targets BEFORE DELETE ON documents
FOR EACH ROW EXECUTE FUNCTION retire_bulk_operation_item_targets();
CREATE TRIGGER import_items_retire_bulk_targets BEFORE DELETE ON import_items
FOR EACH ROW EXECUTE FUNCTION retire_bulk_operation_item_targets();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_bulk_operation_item_update() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.bulk_operation_id <> OLD.bulk_operation_id OR NEW.workspace_id <> OLD.workspace_id
     OR NEW.operation_type <> OLD.operation_type OR NEW.ordinal <> OLD.ordinal
     OR NEW.target_kind <> OLD.target_kind OR NEW.target_public_id <> OLD.target_public_id
     OR NEW.target_display_label <> OLD.target_display_label
     OR NEW.expected_state_snapshot::jsonb <> OLD.expected_state_snapshot::jsonb
     OR NEW.eligibility_status <> OLD.eligibility_status OR NEW.exclusion_reason IS DISTINCT FROM OLD.exclusion_reason THEN
    RAISE EXCEPTION 'bulk membership and preflight are immutable';
  END IF;
  IF OLD.execution_status IN ('succeeded','skipped','failed_permanent','cancelled')
     AND NEW IS DISTINCT FROM OLD THEN RAISE EXCEPTION 'terminal bulk item is immutable'; END IF;
  RETURN NEW;
END; $$;
CREATE TRIGGER bulk_operation_items_update_guard BEFORE UPDATE ON bulk_operation_items
FOR EACH ROW EXECUTE FUNCTION guard_bulk_operation_item_update();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION enforce_bulk_operation_item_incorporation() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE attempt_status text;
BEGIN
  IF NEW.incorporated_attempt_generation IS DISTINCT FROM OLD.incorporated_attempt_generation THEN
    IF OLD.incorporated_attempt_generation IS NOT NULL AND NEW.incorporated_attempt_generation < OLD.incorporated_attempt_generation THEN
      RAISE EXCEPTION 'incorporated attempt generation cannot regress';
    END IF;
    SELECT status INTO attempt_status FROM bulk_operation_item_attempts
      WHERE bulk_operation_item_id = NEW.id AND generation = NEW.incorporated_attempt_generation;
    IF attempt_status IS NULL OR attempt_status = 'open' THEN RAISE EXCEPTION 'only a terminal attempt may be incorporated'; END IF;
  END IF;
  RETURN NEW;
END; $$;
CREATE TRIGGER bulk_operation_items_incorporation_guard BEFORE UPDATE ON bulk_operation_items
FOR EACH ROW EXECUTE FUNCTION enforce_bulk_operation_item_incorporation();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_bulk_attempt_update() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.bulk_operation_item_id <> OLD.bulk_operation_item_id OR NEW.workspace_id <> OLD.workspace_id
     OR NEW.attempt_ordinal <> OLD.attempt_ordinal OR NEW.generation <> OLD.generation
     OR NEW.attempt_token <> OLD.attempt_token OR NEW.invocation_idempotency_key <> OLD.invocation_idempotency_key
     OR NEW.executor_identity <> OLD.executor_identity OR NEW.started_at <> OLD.started_at
     OR NEW.lease_expires_at <> OLD.lease_expires_at THEN RAISE EXCEPTION 'bulk attempt identity is immutable'; END IF;
  IF OLD.status <> 'open' AND NEW IS DISTINCT FROM OLD THEN RAISE EXCEPTION 'terminal bulk attempt is immutable'; END IF;
  RETURN NEW;
END; $$;
CREATE TRIGGER bulk_attempts_update_guard BEFORE UPDATE ON bulk_operation_item_attempts
FOR EACH ROW EXECUTE FUNCTION guard_bulk_attempt_update();

CREATE FUNCTION guard_bulk_subordinate_transition_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'bulk subordinate transitions are immutable'; END; $$;
CREATE TRIGGER bulk_subordinate_transitions_immutable_guard
BEFORE UPDATE OR DELETE ON bulk_operation_item_subordinate_transitions
FOR EACH ROW EXECUTE FUNCTION guard_bulk_subordinate_transition_immutable();
SQL);
    }

    private function reconcileRuntimePrivileges(): void
    {
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rag_platform_app') THEN
    REVOKE INSERT, UPDATE, DELETE ON bulk_operations, bulk_operation_items,
      bulk_operation_item_attempts, bulk_operation_item_subordinate_transitions FROM rag_platform_app;
    GRANT SELECT, INSERT ON bulk_operations TO rag_platform_app;
    GRANT UPDATE (status, membership_digest, confirmed_at, cancellation_requested_at, updated_at) ON bulk_operations TO rag_platform_app;
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
    GRANT USAGE, SELECT ON SEQUENCE bulk_operations_id_seq,
      bulk_operation_items_id_seq, bulk_operation_item_attempts_id_seq,
      bulk_operation_item_subordinate_transitions_id_seq TO rag_platform_app;
  END IF;
END $$;
SQL);
    }
};
