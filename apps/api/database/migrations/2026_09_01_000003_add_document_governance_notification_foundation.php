<?php

declare(strict_types=1);

use App\Enums\DocumentGovernanceEventKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_families', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_assignment_generation')->default(1)->after('owner_user_id');
        });

        Schema::create('document_governance_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 96);
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->json('payload');
            $table->string('correlation_id', 191);
            $table->string('occurrence_key', 191);
            $table->timestampTz('occurred_at');
            $table->timestampTz('claimed_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestampsTz();
            $table->unique(['workspace_id', 'occurrence_key'], 'governance_events_workspace_occurrence_unique');
            $table->index(['published_at', 'failed_at', 'next_attempt_at'], 'governance_events_claimable_index');
        });

        Schema::create('document_governance_event_projections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->uuid('source_event_id');
            $table->string('state', 16)->default('resolving');
            $table->char('resolved_recipient_set_digest', 64)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->timestampsTz();
            $table->unique(['workspace_id', 'source_event_id'], 'governance_event_projections_source_unique');
            $table->unique(['id', 'workspace_id'], 'governance_event_projections_id_workspace_unique');
        });

        Schema::create('document_governance_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('recipient_user_public_id');
            $table->foreignId('recipient_workspace_membership_id')->nullable()->constrained('workspace_memberships')->nullOnDelete();
            $table->string('event_key', 96);
            $table->uuid('source_event_id');
            $table->string('template_key', 96);
            $table->unsignedSmallInteger('template_version')->default(1);
            $table->json('parameters');
            $table->string('severity', 24);
            $table->string('target_kind', 32)->nullable();
            $table->uuid('target_public_id')->nullable();
            $table->string('target_display_label', 255)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->unique(
                ['workspace_id', 'recipient_user_public_id', 'source_event_id'],
                'governance_notifications_recipient_source_unique',
            );
            $table->index(['recipient_user_id', 'dismissed_at', 'read_at', 'created_at'], 'governance_notifications_inbox_index');
        });

        Schema::create('document_governance_notification_projection_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('event_projection_id');
            $table->uuid('recipient_user_public_id');
            $table->string('outcome', 32)->default('pending');
            $table->string('suppression_reason', 48)->nullable();
            $table->uuid('notification_public_id')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->foreign(
                ['event_projection_id', 'workspace_id'],
                'governance_projection_receipts_projection_workspace_foreign',
            )->references(['id', 'workspace_id'])->on('document_governance_event_projections')->restrictOnDelete();
            $table->unique(['event_projection_id', 'recipient_user_public_id'], 'governance_projection_receipts_recipient_unique');
        });

        Schema::create('ownership_eligibility_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->uuid('affected_user_public_id');
            $table->uuid('membership_public_id')->nullable();
            $table->uuid('eligibility_loss_cause_identity');
            $table->unsignedBigInteger('cursor_family_id')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['workspace_id', 'eligibility_loss_cause_identity'], 'ownership_reconciliations_cause_unique');
        });

        Schema::create('user_disablement_reconciliation_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('disabled_at');
            $table->unsignedBigInteger('cursor_membership_id')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresConstraints();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_document_governance_notification_recipient_membership() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_document_family_owner_generation() CASCADE');
        }
        Schema::dropIfExists('user_disablement_reconciliation_sources');
        Schema::dropIfExists('ownership_eligibility_reconciliations');
        Schema::dropIfExists('document_governance_notification_projection_receipts');
        Schema::dropIfExists('document_governance_notifications');
        Schema::dropIfExists('document_governance_event_projections');
        Schema::dropIfExists('document_governance_events');
        Schema::table('document_families', function (Blueprint $table): void {
            $table->dropColumn('owner_assignment_generation');
        });
    }

    private function createPostgresConstraints(): void
    {
        $eventKeys = implode("', '", array_column(DocumentGovernanceEventKey::cases(), 'value'));
        DB::statement("ALTER TABLE document_governance_events ADD CONSTRAINT governance_events_key_check CHECK (event_key IN ('{$eventKeys}'))");
        DB::statement('ALTER TABLE document_governance_events ADD CONSTRAINT governance_events_terminal_check CHECK (NOT (published_at IS NOT NULL AND failed_at IS NOT NULL))');
        DB::statement("ALTER TABLE document_governance_event_projections ADD CONSTRAINT governance_event_projections_state_check CHECK (state IN ('resolving','projecting','completed','failed'))");
        DB::statement("ALTER TABLE document_governance_event_projections ADD CONSTRAINT governance_event_projections_shape_check CHECK ((state = 'resolving' AND resolved_recipient_set_digest IS NULL AND completed_at IS NULL) OR (state = 'projecting' AND resolved_recipient_set_digest IS NOT NULL AND completed_at IS NULL) OR (state IN ('completed','failed') AND resolved_recipient_set_digest IS NOT NULL AND completed_at IS NOT NULL))");
        DB::statement("ALTER TABLE document_governance_notifications ADD CONSTRAINT governance_notifications_severity_check CHECK (severity IN ('info','action_required','warning'))");
        DB::statement("ALTER TABLE document_governance_notification_projection_receipts ADD CONSTRAINT governance_projection_receipts_outcome_check CHECK ((outcome = 'pending' AND suppression_reason IS NULL AND notification_public_id IS NULL AND resolved_at IS NULL) OR (outcome = 'notification_created' AND suppression_reason IS NULL AND notification_public_id IS NOT NULL AND resolved_at IS NOT NULL) OR (outcome = 'suppressed' AND suppression_reason IS NOT NULL AND notification_public_id IS NULL AND resolved_at IS NOT NULL))");
        DB::statement('ALTER TABLE document_families ADD CONSTRAINT document_families_owner_generation_positive_check CHECK (owner_assignment_generation > 0)');

        DB::unprepared(<<<'SQL'
CREATE FUNCTION enforce_document_family_owner_generation() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.owner_assignment_generation <> OLD.owner_assignment_generation
     AND (NEW.owner_user_id IS NOT DISTINCT FROM OLD.owner_user_id
          OR NEW.owner_assignment_generation <> OLD.owner_assignment_generation + 1) THEN
    RAISE EXCEPTION 'owner assignment generation must advance exactly once with an owner change';
  END IF;
  IF NEW.owner_user_id IS DISTINCT FROM OLD.owner_user_id
     AND NEW.owner_assignment_generation <> OLD.owner_assignment_generation + 1 THEN
    RAISE EXCEPTION 'owner changes must advance owner assignment generation';
  END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER enforce_document_family_owner_generation
BEFORE UPDATE ON document_families FOR EACH ROW
EXECUTE FUNCTION enforce_document_family_owner_generation();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION enforce_document_governance_notification_recipient_membership() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE membership_workspace bigint; membership_user bigint;
BEGIN
  IF TG_OP = 'UPDATE' AND NEW.recipient_user_public_id IS DISTINCT FROM OLD.recipient_user_public_id THEN
    RAISE EXCEPTION 'notification recipient identity is immutable';
  END IF;
  IF NEW.recipient_workspace_membership_id IS NOT NULL THEN
    SELECT workspace_id, user_id INTO membership_workspace, membership_user
    FROM workspace_memberships WHERE id = NEW.recipient_workspace_membership_id;
    IF NOT FOUND OR membership_workspace <> NEW.workspace_id OR membership_user <> NEW.recipient_user_id THEN
      RAISE EXCEPTION 'notification membership does not match workspace and recipient';
    END IF;
  END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER enforce_document_governance_notification_recipient_membership
BEFORE INSERT OR UPDATE ON document_governance_notifications FOR EACH ROW
EXECUTE FUNCTION enforce_document_governance_notification_recipient_membership();
SQL);
    }
};
