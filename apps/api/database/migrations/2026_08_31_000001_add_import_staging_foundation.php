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
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('open');
            $table->timestampTz('retention_expires_at');
            $table->timestampsTz();

            $table->unique(['id', 'workspace_id'], 'import_batches_id_workspace_unique');
            $table->index(['workspace_id', 'status', 'retention_expires_at'], 'import_batches_scope_status_expiry');
        });

        Schema::create('import_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('import_batch_id');
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('staged_object_key')->unique();
            $table->char('source_checksum_sha256', 64)->nullable();
            $table->string('media_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('preflight_status')->default('pending');
            $table->string('preflight_rejection_reason')->nullable();
            $table->string('match_status')->default('pending');
            $table->unsignedBigInteger('current_decision_snapshot_id')->nullable();
            $table->unsignedBigInteger('replaced_by_import_item_id')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'workspace_id'], 'import_items_id_workspace_unique');
            $table->unique(['id', 'import_batch_id', 'workspace_id'], 'import_items_id_batch_workspace_unique');
            $table->foreign(['import_batch_id', 'workspace_id'], 'import_items_batch_workspace_foreign')
                ->references(['id', 'workspace_id'])
                ->on('import_batches')
                ->restrictOnDelete();
            $table->foreign(
                ['replaced_by_import_item_id', 'import_batch_id', 'workspace_id'],
                'import_items_replacement_lineage_foreign',
            )->references(['id', 'import_batch_id', 'workspace_id'])
                ->on('import_items')
                ->restrictOnDelete();
            $table->index(['workspace_id', 'preflight_status', 'match_status'], 'import_items_scope_readiness');
        });

        Schema::create('import_decision_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('import_item_id')->constrained('import_items')->restrictOnDelete();
            $table->unsignedInteger('schema_version');
            $table->text('canonical_definition');
            $table->char('digest_sha256', 64);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['id', 'import_item_id'], 'import_decision_snapshots_id_item_unique');
            $table->unique(['import_item_id', 'digest_sha256'], 'import_decision_snapshots_item_digest_unique');
        });

        Schema::table('import_items', function (Blueprint $table): void {
            $table->foreign(['current_decision_snapshot_id', 'id'], 'import_items_current_decision_foreign')
                ->references(['id', 'import_item_id'])
                ->on('import_decision_snapshots')
                ->restrictOnDelete();
        });

        Schema::create('promotion_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('import_item_id');
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('decision_snapshot_id');
            $table->unsignedInteger('attempt_ordinal');
            $table->string('status')->default('reserved');
            $table->string('reserved_object_key');
            $table->json('checksum_evidence')->nullable();
            $table->char('lease_token_hash', 64)->nullable();
            $table->unsignedInteger('lease_generation')->default(0);
            $table->timestampTz('lease_expires_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestampTz('cancellation_requested_at')->nullable();
            $table->string('actor_type');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('system_actor_code')->nullable();
            $table->string('actor_identity')->storedAs(
                "CASE WHEN actor_type = 'human' THEN 'user:' || CAST(actor_user_id AS TEXT) ELSE 'system:' || system_actor_code END"
            );
            $table->string('operation_kind');
            $table->string('client_idempotency_key', 128);
            $table->char('request_digest_sha256', 64);
            $table->timestampsTz();

            $table->foreign(['import_item_id', 'workspace_id'], 'promotion_attempts_item_workspace_foreign')
                ->references(['id', 'workspace_id'])
                ->on('import_items')
                ->restrictOnDelete();
            $table->foreign(['decision_snapshot_id', 'import_item_id'], 'promotion_attempts_snapshot_item_foreign')
                ->references(['id', 'import_item_id'])
                ->on('import_decision_snapshots')
                ->restrictOnDelete();
            $table->unique(['import_item_id', 'attempt_ordinal'], 'promotion_attempts_item_ordinal_unique');
            $table->unique(
                ['workspace_id', 'import_item_id', 'actor_identity', 'operation_kind', 'client_idempotency_key'],
                'promotion_attempts_idempotency_unique',
            );
            $table->index(['workspace_id', 'status'], 'promotion_attempts_scope_status');
        });

        Schema::create('promotion_attempt_failures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_attempt_id')->constrained('promotion_attempts')->restrictOnDelete();
            $table->unsignedInteger('lease_generation');
            $table->string('failure_code');
            $table->json('safe_context')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(
                ['promotion_attempt_id', 'lease_generation'],
                'promotion_attempt_failures_attempt_generation_unique',
            );
        });

        Schema::create('workspace_checksum_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->char('source_checksum_sha256', 64);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(
                ['workspace_id', 'source_checksum_sha256'],
                'workspace_checksum_reservations_scope_digest_unique',
            );
        });

        DB::statement(
            "CREATE UNIQUE INDEX promotion_attempts_one_open_per_item_unique
             ON promotion_attempts (import_item_id)
             WHERE status IN ('reserved', 'copying', 'source_verified')"
        );

        if (DB::getDriverName() === 'pgsql') {
            $this->addPostgresConstraints();
            $this->addPostgresGuards();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS sync_promotion_attempt_failure_count() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_promotion_attempt_update() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_import_decision_snapshot_immutable() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_import_item_update() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_import_batch_update() CASCADE');
        }

        Schema::dropIfExists('workspace_checksum_reservations');
        Schema::dropIfExists('promotion_attempt_failures');
        DB::statement('DROP INDEX IF EXISTS promotion_attempts_one_open_per_item_unique');
        Schema::dropIfExists('promotion_attempts');
        Schema::table('import_items', function (Blueprint $table): void {
            $table->dropForeign('import_items_current_decision_foreign');
        });
        Schema::dropIfExists('import_decision_snapshots');
        Schema::dropIfExists('import_items');
        Schema::dropIfExists('import_batches');
    }

    private function addPostgresConstraints(): void
    {
        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_status_check CHECK (status IN ('open', 'resolved', 'expired'))");
        DB::statement("ALTER TABLE import_items ADD CONSTRAINT import_items_preflight_status_check CHECK (preflight_status IN ('pending', 'verified', 'rejected'))");
        DB::statement("ALTER TABLE import_items ADD CONSTRAINT import_items_preflight_result_check CHECK ((preflight_status = 'pending' AND source_checksum_sha256 IS NULL AND media_type IS NULL AND size_bytes IS NULL AND preflight_rejection_reason IS NULL) OR (preflight_status = 'verified' AND source_checksum_sha256 ~ '^[0-9a-f]{64}$' AND media_type IS NOT NULL AND size_bytes IS NOT NULL AND preflight_rejection_reason IS NULL) OR (preflight_status = 'rejected' AND preflight_rejection_reason IN ('password_protected', 'encrypted', 'corrupt_structure', 'mime_mismatch')))");
        DB::statement("ALTER TABLE import_items ADD CONSTRAINT import_items_match_status_check CHECK (match_status IN ('pending', 'resolved'))");
        DB::statement('ALTER TABLE import_items ADD CONSTRAINT import_items_size_non_negative CHECK (size_bytes IS NULL OR size_bytes >= 0)');
        DB::statement('ALTER TABLE import_items ADD CONSTRAINT import_items_not_self_replaced_check CHECK (replaced_by_import_item_id IS NULL OR replaced_by_import_item_id <> id)');
        DB::statement("ALTER TABLE import_decision_snapshots ADD CONSTRAINT import_decision_snapshots_digest_check CHECK (digest_sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE import_decision_snapshots ADD CONSTRAINT import_decision_snapshots_schema_version_check CHECK (schema_version > 0)');
        DB::statement("ALTER TABLE promotion_attempts ADD CONSTRAINT promotion_attempts_status_check CHECK (status IN ('reserved', 'copying', 'source_verified', 'committed', 'conflict', 'failed', 'abandoned', 'expired'))");
        DB::statement("ALTER TABLE promotion_attempts ADD CONSTRAINT promotion_attempts_actor_check CHECK ((actor_type = 'human' AND actor_user_id IS NOT NULL AND system_actor_code IS NULL) OR (actor_type = 'system' AND actor_user_id IS NULL AND system_actor_code IN ('promotion_reconciler', 'retention_sweep', 'legacy_drain_reconciler')))");
        DB::statement("ALTER TABLE promotion_attempts ADD CONSTRAINT promotion_attempts_operation_check CHECK (operation_kind IN ('promote', 'retry', 'adopt'))");
        DB::statement("ALTER TABLE promotion_attempts ADD CONSTRAINT promotion_attempts_digest_check CHECK (request_digest_sha256 ~ '^[0-9a-f]{64}$' AND (lease_token_hash IS NULL OR lease_token_hash ~ '^[0-9a-f]{64}$'))");
        DB::statement('ALTER TABLE promotion_attempts ADD CONSTRAINT promotion_attempts_numeric_check CHECK (attempt_ordinal > 0 AND failure_count >= 0)');
        DB::statement("ALTER TABLE workspace_checksum_reservations ADD CONSTRAINT workspace_checksum_reservations_digest_check CHECK (source_checksum_sha256 ~ '^[0-9a-f]{64}$')");
    }

    private function addPostgresGuards(): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_import_batch_update() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.public_id <> OLD.public_id
        OR NEW.workspace_id <> OLD.workspace_id
        OR NEW.initiated_by_user_id <> OLD.initiated_by_user_id
        OR NEW.retention_expires_at <> OLD.retention_expires_at THEN
        RAISE EXCEPTION 'import batch identity is immutable';
    END IF;
    IF OLD.status = 'expired' AND NEW.status <> OLD.status THEN
        RAISE EXCEPTION 'expired import batches are terminal';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER import_batches_update_guard
BEFORE UPDATE ON import_batches
FOR EACH ROW EXECUTE FUNCTION guard_import_batch_update();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_import_item_update() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.public_id <> OLD.public_id
        OR NEW.import_batch_id <> OLD.import_batch_id
        OR NEW.workspace_id <> OLD.workspace_id
        OR NEW.staged_object_key <> OLD.staged_object_key THEN
        RAISE EXCEPTION 'import item identity is immutable';
    END IF;
    IF OLD.preflight_status = 'verified' AND (
        NEW.source_checksum_sha256 IS DISTINCT FROM OLD.source_checksum_sha256
        OR NEW.media_type IS DISTINCT FROM OLD.media_type
        OR NEW.size_bytes IS DISTINCT FROM OLD.size_bytes
        OR NEW.preflight_status <> OLD.preflight_status
    ) THEN
        RAISE EXCEPTION 'verified import source identity is immutable';
    END IF;
    IF OLD.replaced_by_import_item_id IS NOT NULL
        AND NEW.replaced_by_import_item_id IS DISTINCT FROM OLD.replaced_by_import_item_id THEN
        RAISE EXCEPTION 'replacement lineage is set once';
    END IF;
    IF OLD.current_decision_snapshot_id IS NOT NULL
        AND NEW.current_decision_snapshot_id IS DISTINCT FROM OLD.current_decision_snapshot_id
        AND (NEW.current_decision_snapshot_id IS NULL
            OR NEW.current_decision_snapshot_id <= OLD.current_decision_snapshot_id) THEN
        RAISE EXCEPTION 'current decision pointer moves forward only';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER import_items_update_guard
BEFORE UPDATE ON import_items
FOR EACH ROW EXECUTE FUNCTION guard_import_item_update();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_import_decision_snapshot_immutable() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'import decision snapshots are immutable';
END;
$$;
CREATE TRIGGER import_decision_snapshots_update_guard
BEFORE UPDATE OR DELETE ON import_decision_snapshots
FOR EACH ROW EXECUTE FUNCTION guard_import_decision_snapshot_immutable();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_promotion_attempt_update() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.public_id <> OLD.public_id
        OR NEW.import_item_id <> OLD.import_item_id
        OR NEW.workspace_id <> OLD.workspace_id
        OR NEW.decision_snapshot_id <> OLD.decision_snapshot_id
        OR NEW.attempt_ordinal <> OLD.attempt_ordinal
        OR NEW.reserved_object_key <> OLD.reserved_object_key
        OR NEW.actor_type <> OLD.actor_type
        OR NEW.actor_user_id IS DISTINCT FROM OLD.actor_user_id
        OR NEW.system_actor_code IS DISTINCT FROM OLD.system_actor_code
        OR NEW.operation_kind <> OLD.operation_kind
        OR NEW.client_idempotency_key <> OLD.client_idempotency_key
        OR NEW.request_digest_sha256 <> OLD.request_digest_sha256 THEN
        RAISE EXCEPTION 'promotion attempt identity is immutable';
    END IF;
    IF NEW.failure_count <> OLD.failure_count AND pg_trigger_depth() < 2 THEN
        RAISE EXCEPTION 'promotion attempt failure count is derived';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER promotion_attempts_update_guard
BEFORE UPDATE ON promotion_attempts
FOR EACH ROW EXECUTE FUNCTION guard_promotion_attempt_update();
SQL);

        DB::unprepared(<<<'SQL'
CREATE FUNCTION sync_promotion_attempt_failure_count() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    affected_attempt_id bigint;
BEGIN
    affected_attempt_id := COALESCE(NEW.promotion_attempt_id, OLD.promotion_attempt_id);
    UPDATE promotion_attempts
       SET failure_count = (
           SELECT COUNT(*)
             FROM promotion_attempt_failures
            WHERE promotion_attempt_id = affected_attempt_id
       )
     WHERE id = affected_attempt_id;
    RETURN COALESCE(NEW, OLD);
END;
$$;
CREATE TRIGGER promotion_attempt_failures_count_sync
AFTER INSERT OR DELETE ON promotion_attempt_failures
FOR EACH ROW EXECUTE FUNCTION sync_promotion_attempt_failure_count();
SQL);
    }
};
