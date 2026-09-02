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
        Schema::create('workspace_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('email_delivery_enabled')->default(true);
            $table->boolean('default_email_enabled')->default(true);
            $table->timestampsTz();
        });

        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('category_group', 96);
            $table->boolean('email_enabled');
            $table->timestampsTz();
            $table->unique(['user_id', 'category_group'], 'user_notification_preferences_category_unique');
        });

        Schema::create('document_governance_email_envelopes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->uuid('recipient_user_public_id');
            $table->string('category_group', 96);
            $table->date('digest_date')->nullable();
            $table->string('envelope_key', 191);
            $table->string('assembly_status', 32)->default('assembling');
            $table->timestampTz('sealed_at')->nullable();
            $table->char('sealed_membership_digest', 64)->nullable();
            $table->string('template_key', 96)->nullable();
            $table->unsignedSmallInteger('template_version')->nullable();
            $table->string('branding_configuration_identity', 191)->nullable();
            $table->string('workspace_display_name_snapshot', 255)->nullable();
            $table->string('resolved_accent_identity', 64)->nullable();
            $table->char('sealed_rendering_basis_digest', 64)->nullable();
            $table->char('dispatch_decision_digest', 64)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('terminal_at')->nullable();
            $table->string('suppression_reason', 64)->nullable();
            $table->string('terminal_failure_category', 64)->nullable();
            $table->timestampsTz();
            $table->unique(['workspace_id', 'envelope_key'], 'governance_email_envelopes_workspace_key_unique');
            $table->unique(['id', 'workspace_id'], 'governance_email_envelopes_id_workspace_unique');
            $table->index(['assembly_status', 'next_attempt_at'], 'governance_email_envelopes_claimable_index');
        });

        Schema::create('document_governance_email_envelope_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('envelope_id');
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->uuid('source_event_id');
            $table->uuid('recipient_user_public_id');
            $table->unsignedSmallInteger('ordinal')->nullable();
            $table->timestampTz('added_at');
            $table->timestampsTz();
            $table->unique('notification_id', 'governance_email_members_notification_unique');
            $table->unique(['envelope_id', 'ordinal'], 'governance_email_members_ordinal_unique');
            $table->foreign('envelope_id', 'governance_email_members_envelope_foreign')
                ->references('id')->on('document_governance_email_envelopes')->restrictOnDelete();
            $table->foreign('notification_id', 'governance_email_members_notification_foreign')
                ->references('id')->on('document_governance_notifications')->nullOnDelete();
        });

        Schema::create('document_governance_email_envelope_member_decisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('envelope_member_id');
            $table->string('decision', 16);
            $table->string('suppression_reason', 64)->nullable();
            $table->timestampTz('decided_at');
            $table->timestampsTz();
            $table->unique('envelope_member_id', 'governance_email_member_decisions_member_unique');
            $table->foreign('envelope_member_id', 'governance_email_member_decisions_member_foreign')
                ->references('id')->on('document_governance_email_envelope_members')->restrictOnDelete();
        });

        Schema::create('document_governance_email_envelope_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('envelope_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedSmallInteger('generation');
            $table->uuid('attempt_token');
            $table->string('status', 32);
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('completed_at')->nullable();
            $table->string('failure_category', 64)->nullable();
            $table->string('provider_idempotency_key_used', 191);
            $table->string('provider_message_id', 191)->nullable();
            $table->char('sealed_rendering_basis_digest_verified', 64)->nullable();
            $table->char('dispatch_decision_digest_verified', 64)->nullable();
            $table->timestampsTz();
            $table->foreign(['envelope_id', 'workspace_id'], 'governance_email_attempts_envelope_workspace_foreign')
                ->references(['id', 'workspace_id'])->on('document_governance_email_envelopes')->restrictOnDelete();
            $table->unique(['envelope_id', 'generation'], 'governance_email_attempts_generation_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresConstraints();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_governance_email_member_append() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_governance_email_terminal_immutability() CASCADE');
        }
        Schema::dropIfExists('document_governance_email_envelope_attempts');
        Schema::dropIfExists('document_governance_email_envelope_member_decisions');
        Schema::dropIfExists('document_governance_email_envelope_members');
        Schema::dropIfExists('document_governance_email_envelopes');
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('workspace_notification_settings');
    }

    private function createPostgresConstraints(): void
    {
        DB::statement("CREATE UNIQUE INDEX governance_email_attempts_one_open_unique ON document_governance_email_envelope_attempts (envelope_id) WHERE status = 'open'");
        DB::statement("ALTER TABLE document_governance_email_envelope_attempts ADD CONSTRAINT governance_email_attempts_shape_check CHECK ((status = 'open' AND lease_expires_at IS NOT NULL AND completed_at IS NULL AND failure_category IS NULL AND provider_message_id IS NULL) OR (status = 'accepted' AND lease_expires_at IS NULL AND completed_at IS NOT NULL AND failure_category IS NULL AND provider_message_id IS NOT NULL) OR (status IN ('failed_retryable','failed_permanent','abandoned') AND lease_expires_at IS NULL AND completed_at IS NOT NULL AND failure_category IS NOT NULL AND provider_message_id IS NULL))");
        DB::statement("ALTER TABLE document_governance_email_envelope_member_decisions ADD CONSTRAINT governance_email_member_decisions_shape_check CHECK ((decision = 'included' AND suppression_reason IS NULL) OR (decision = 'suppressed' AND suppression_reason IS NOT NULL))");
        DB::statement("ALTER TABLE document_governance_email_envelopes ADD CONSTRAINT governance_email_envelopes_shape_check CHECK ((assembly_status = 'assembling' AND sealed_rendering_basis_digest IS NULL AND dispatch_decision_digest IS NULL AND terminal_at IS NULL AND dispatched_at IS NULL AND suppression_reason IS NULL AND terminal_failure_category IS NULL) OR (assembly_status = 'ready' AND sealed_rendering_basis_digest IS NOT NULL AND terminal_at IS NULL AND dispatched_at IS NULL AND suppression_reason IS NULL AND terminal_failure_category IS NULL) OR (assembly_status = 'dispatching' AND sealed_rendering_basis_digest IS NOT NULL AND dispatch_decision_digest IS NOT NULL AND terminal_at IS NULL AND dispatched_at IS NULL AND suppression_reason IS NULL AND terminal_failure_category IS NULL) OR (assembly_status = 'sent' AND sealed_rendering_basis_digest IS NOT NULL AND dispatch_decision_digest IS NOT NULL AND terminal_at IS NOT NULL AND dispatched_at IS NOT NULL AND suppression_reason IS NULL AND terminal_failure_category IS NULL) OR (assembly_status = 'failed_permanent' AND sealed_rendering_basis_digest IS NOT NULL AND dispatch_decision_digest IS NOT NULL AND terminal_at IS NOT NULL AND dispatched_at IS NULL AND suppression_reason IS NULL AND terminal_failure_category IS NOT NULL) OR (assembly_status = 'suppressed' AND sealed_rendering_basis_digest IS NOT NULL AND dispatch_decision_digest IS NOT NULL AND terminal_at IS NOT NULL AND dispatched_at IS NULL AND suppression_reason IS NOT NULL AND terminal_failure_category IS NULL))");
        DB::unprepared(<<<'SQL'
CREATE FUNCTION enforce_governance_email_member_append() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE parent_status text; parent_terminal_at timestamptz;
BEGIN
  IF TG_OP = 'UPDATE' THEN
    RAISE EXCEPTION 'governance email envelope membership is append-only';
  END IF;
  SELECT assembly_status, terminal_at INTO parent_status, parent_terminal_at
  FROM document_governance_email_envelopes
  WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.envelope_id ELSE NEW.envelope_id END FOR UPDATE;
  IF TG_OP = 'DELETE' THEN
    IF parent_status NOT IN ('sent', 'failed_permanent', 'suppressed')
       OR parent_terminal_at > now() - interval '400 days' THEN
      RAISE EXCEPTION 'governance email members may be purged only with an expired terminal envelope';
    END IF;
    RETURN OLD;
  END IF;
  IF parent_status IS DISTINCT FROM 'assembling' THEN
    RAISE EXCEPTION 'governance email members may be appended only while assembling';
  END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER enforce_governance_email_member_append
BEFORE INSERT OR UPDATE OR DELETE ON document_governance_email_envelope_members
FOR EACH ROW EXECUTE FUNCTION enforce_governance_email_member_append();

CREATE FUNCTION enforce_governance_email_terminal_immutability() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF OLD.terminal_at IS NOT NULL AND NEW IS DISTINCT FROM OLD THEN
    RAISE EXCEPTION 'terminal governance email envelope evidence is immutable';
  END IF;
  IF OLD.sealed_rendering_basis_digest IS NOT NULL
     AND NEW.sealed_rendering_basis_digest IS DISTINCT FROM OLD.sealed_rendering_basis_digest THEN
    RAISE EXCEPTION 'sealed rendering basis digest is immutable';
  END IF;
  IF OLD.dispatch_decision_digest IS NOT NULL
     AND NEW.dispatch_decision_digest IS DISTINCT FROM OLD.dispatch_decision_digest THEN
    RAISE EXCEPTION 'dispatch decision digest is immutable';
  END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER enforce_governance_email_terminal_immutability
BEFORE UPDATE ON document_governance_email_envelopes
FOR EACH ROW EXECUTE FUNCTION enforce_governance_email_terminal_immutability();
SQL);
    }
};
