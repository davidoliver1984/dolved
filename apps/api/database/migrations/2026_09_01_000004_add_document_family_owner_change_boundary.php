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
        Schema::table('document_governance_commands', function (Blueprint $table): void {
            $table->string('purpose')->change();
            $table->string('target_kind')->change();
            $table->unsignedBigInteger('target_document_id')->nullable()->change();
            $table->string('target_state_at_creation')->nullable()->change();
            $table->unsignedBigInteger('target_document_family_id')->nullable()->after('target_document_id');
            $table->uuid('target_document_family_public_id')->nullable()->after('target_document_family_id');
            $table->foreignId('expected_current_owner_user_id')->nullable()->after('target_document_family_public_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('expected_current_generation')->nullable()->after('expected_current_owner_user_id');
            $table->foreignId('intended_new_owner_user_id')->nullable()->after('expected_current_generation')->constrained('users')->restrictOnDelete();
            $table->json('result')->nullable()->after('result_document_id');
            $table->timestampTz('completed_at')->nullable()->after('result');
            $table->foreign(
                ['target_document_family_id', 'workspace_id'],
                'document_governance_commands_family_workspace_foreign',
            )->references(['id', 'workspace_id'])->on('document_families')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_governance_commands DROP CONSTRAINT IF EXISTS document_governance_commands_purpose_check');
            DB::statement('ALTER TABLE document_governance_commands DROP CONSTRAINT IF EXISTS document_governance_commands_target_kind_check');
            DB::statement("ALTER TABLE document_governance_commands ADD CONSTRAINT document_governance_commands_purpose_check CHECK (purpose IN ('approve','withdraw','reschedule','correct_timestamps','applicability_successor','document_family.owner.change'))");
            DB::statement("ALTER TABLE document_governance_commands ADD CONSTRAINT document_governance_commands_target_kind_check CHECK (target_kind IN ('document_version','document_family'))");
            DB::statement("ALTER TABLE document_governance_commands ADD CONSTRAINT document_governance_commands_owner_change_shape_check CHECK ((purpose = 'document_family.owner.change' AND target_document_id IS NULL AND target_state_at_creation IS NULL AND target_document_family_public_id IS NOT NULL AND expected_current_owner_user_id IS NOT NULL AND expected_current_generation IS NOT NULL AND intended_new_owner_user_id IS NOT NULL AND result_document_id IS NULL) OR (purpose <> 'document_family.owner.change' AND target_document_id IS NOT NULL AND target_state_at_creation IS NOT NULL AND target_document_family_id IS NULL AND target_document_family_public_id IS NULL AND expected_current_owner_user_id IS NULL AND expected_current_generation IS NULL AND intended_new_owner_user_id IS NULL AND result IS NULL AND completed_at IS NULL))");
            DB::statement('ALTER TABLE document_governance_commands ADD CONSTRAINT document_governance_commands_result_shape_check CHECK ((result IS NULL AND completed_at IS NULL) OR (result IS NOT NULL AND completed_at IS NOT NULL))');

            DB::statement('ALTER TABLE document_governance_commands DROP CONSTRAINT document_governance_commands_family_workspace_foreign');
            DB::statement(<<<'SQL'
ALTER TABLE document_governance_commands
  ADD CONSTRAINT document_governance_commands_family_workspace_foreign
  FOREIGN KEY (target_document_family_id, workspace_id)
  REFERENCES document_families (id, workspace_id)
  ON DELETE SET NULL (target_document_family_id)
SQL);
            $this->createPostgresBoundary();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS apply_document_family_owner_change(bigint)');
            DB::statement('DROP FUNCTION IF EXISTS enforce_document_governance_command_target_shape() CASCADE');
            DB::statement('ALTER TABLE document_governance_commands DROP CONSTRAINT IF EXISTS document_governance_commands_result_shape_check');
            DB::statement('ALTER TABLE document_governance_commands DROP CONSTRAINT IF EXISTS document_governance_commands_owner_change_shape_check');
        }
        Schema::table('document_governance_commands', function (Blueprint $table): void {
            $table->dropForeign('document_governance_commands_family_workspace_foreign');
            $table->dropForeign(['expected_current_owner_user_id']);
            $table->dropForeign(['intended_new_owner_user_id']);
            $table->dropColumn([
                'target_document_family_id', 'target_document_family_public_id',
                'expected_current_owner_user_id', 'expected_current_generation',
                'intended_new_owner_user_id', 'result', 'completed_at',
            ]);
        });
    }

    private function createPostgresBoundary(): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION enforce_document_governance_command_target_shape() RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
BEGIN
  IF NEW.purpose = 'document_family.owner.change'
     AND (NEW.target_document_family_id IS NULL
       OR NEW.target_document_family_public_id IS NULL
       OR NEW.expected_current_owner_user_id IS NULL
       OR NEW.expected_current_generation IS NULL
       OR NEW.intended_new_owner_user_id IS NULL
       OR NEW.request_payload_digest IS NULL) THEN
    RAISE EXCEPTION USING ERRCODE = '23000', MESSAGE = 'owner_change_command_incomplete_at_creation';
  END IF;
  RETURN NEW;
END
$$;

CREATE TRIGGER enforce_document_governance_command_target_shape
BEFORE INSERT ON document_governance_commands
FOR EACH ROW EXECUTE FUNCTION enforce_document_governance_command_target_shape();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION apply_document_family_owner_change(p_command_id bigint) RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  command_row public.document_governance_commands%ROWTYPE;
  family_row public.document_families%ROWTYPE;
  final_result jsonb;
  audit_public_id uuid;
BEGIN
  SELECT * INTO command_row
  FROM public.document_governance_commands
  WHERE id = p_command_id
  FOR UPDATE;

  IF NOT FOUND THEN
    RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'owner_change_command_missing';
  END IF;
  IF command_row.purpose <> 'document_family.owner.change' THEN
    RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'owner_change_purpose_invalid';
  END IF;
  IF command_row.completed_at IS NOT NULL OR command_row.result IS NOT NULL THEN
    RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'owner_change_command_already_completed';
  END IF;
  IF command_row.request_payload_digest IS NULL THEN
    RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'owner_change_command_incomplete';
  END IF;

  SELECT * INTO family_row
  FROM public.document_families
  WHERE id = command_row.target_document_family_id
  FOR UPDATE;

  IF NOT FOUND THEN
    RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'owner_change_target_family_missing';
  END IF;
  IF command_row.workspace_id <> family_row.workspace_id THEN
    RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'owner_change_workspace_mismatch';
  END IF;

  IF family_row.owner_user_id = command_row.intended_new_owner_user_id THEN
    final_result := jsonb_build_object(
      'changed', false,
      'owner_user_id', family_row.owner_user_id,
      'owner_assignment_generation', family_row.owner_assignment_generation
    );
  ELSE
    IF family_row.owner_user_id <> command_row.expected_current_owner_user_id
       OR family_row.owner_assignment_generation <> command_row.expected_current_generation THEN
      RAISE EXCEPTION USING ERRCODE = 'P0001', MESSAGE = 'owner_change_precondition_stale';
    END IF;

    UPDATE public.document_families
    SET owner_user_id = command_row.intended_new_owner_user_id,
        owner_assignment_generation = family_row.owner_assignment_generation + 1,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = family_row.id;

    audit_public_id := gen_random_uuid();
    INSERT INTO public.document_governance_audit_events (
      public_id, workspace_id, document_family_id, document_id, target_scope,
      actor_type, actor_user_id, system_actor_code, action, reason,
      previous_values, new_values, occurred_at, created_at, updated_at
    ) VALUES (
      audit_public_id, family_row.workspace_id, family_row.id, NULL, 'family',
      'human', command_row.actor_user_id, NULL, 'document_family_owner_changed', NULL,
      jsonb_build_object('owner_user_id', family_row.owner_user_id, 'owner_assignment_generation', family_row.owner_assignment_generation),
      jsonb_build_object('owner_user_id', command_row.intended_new_owner_user_id, 'owner_assignment_generation', family_row.owner_assignment_generation + 1),
      CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
    );

    final_result := jsonb_build_object(
      'changed', true,
      'owner_user_id', command_row.intended_new_owner_user_id,
      'owner_assignment_generation', family_row.owner_assignment_generation + 1
    );
  END IF;

  UPDATE public.document_governance_commands
  SET status = 'completed', result = final_result, completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
  WHERE id = command_row.id;

  RETURN final_result;
END
$$;

REVOKE ALL ON FUNCTION enforce_document_governance_command_target_shape() FROM PUBLIC;
REVOKE ALL ON FUNCTION apply_document_family_owner_change(bigint) FROM PUBLIC;
SQL);

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rag_platform_owner') THEN
    ALTER FUNCTION enforce_document_governance_command_target_shape() OWNER TO rag_platform_owner;
    ALTER FUNCTION apply_document_family_owner_change(bigint) OWNER TO rag_platform_owner;
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rag_platform_app') THEN
    REVOKE UPDATE ON document_governance_commands FROM rag_platform_app;
    REVOKE UPDATE, INSERT ON document_families FROM rag_platform_app;
    GRANT SELECT ON document_governance_commands TO rag_platform_app;
    GRANT INSERT (public_id, workspace_id, purpose, idempotency_key, actor_user_id,
      target_kind, target_document_id, target_state_at_creation,
      target_document_family_id, target_document_family_public_id,
      expected_current_owner_user_id, expected_current_generation,
      intended_new_owner_user_id, request_payload_digest, status,
      result_document_id, created_at, updated_at)
      ON document_governance_commands TO rag_platform_app;
    GRANT INSERT (public_id, workspace_id, name, description, category_id,
      owner_user_id, review_due_date, tombstoned_at, created_at, updated_at)
      ON document_families TO rag_platform_app;
    GRANT UPDATE (name, description, category_id, review_due_date,
      tombstoned_at, updated_at)
      ON document_families TO rag_platform_app;
    GRANT EXECUTE ON FUNCTION apply_document_family_owner_change(bigint) TO rag_platform_app;
  END IF;
END
$$;
SQL);
    }
};
