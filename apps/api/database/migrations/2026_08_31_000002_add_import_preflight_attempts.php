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
        Schema::table('import_items', function (Blueprint $table): void {
            $table->string('declared_media_type')->nullable()->after('staged_object_key');
        });

        Schema::create('import_preflight_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->unsignedBigInteger('import_item_id');
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('lease_generation');
            $table->char('lease_token_hash', 64);
            $table->timestampTz('lease_expires_at');
            $table->string('staged_object_key');
            $table->string('declared_media_type');
            $table->string('status')->default('open');
            $table->string('result')->nullable();
            $table->string('diagnostic_code')->nullable();
            $table->char('reported_payload_sha256', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['import_item_id', 'workspace_id'], 'import_preflight_attempts_item_workspace_foreign')
                ->references(['id', 'workspace_id'])
                ->on('import_items')
                ->restrictOnDelete();
            $table->unique(['import_item_id', 'lease_generation'], 'import_preflight_attempts_item_generation_unique');
            $table->index(['status', 'lease_expires_at'], 'import_preflight_attempts_reclaim_lookup');
        });

        DB::statement("CREATE UNIQUE INDEX import_preflight_attempts_one_open_per_item_unique ON import_preflight_attempts (import_item_id) WHERE status = 'open'");

        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->uuid('import_item_public_id')->nullable()->index()->after('document_public_id');
            $table->uuid('document_public_id')->nullable()->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE import_items DROP CONSTRAINT import_items_preflight_result_check');
            DB::statement("ALTER TABLE import_items ADD CONSTRAINT import_items_preflight_result_check CHECK ((preflight_status = 'pending' AND source_checksum_sha256 IS NULL AND media_type IS NULL AND size_bytes IS NULL AND preflight_rejection_reason IS NULL) OR (preflight_status = 'verified' AND source_checksum_sha256 ~ '^[0-9a-f]{64}$' AND media_type IS NOT NULL AND size_bytes IS NOT NULL AND preflight_rejection_reason IS NULL) OR (preflight_status = 'rejected' AND preflight_rejection_reason IN ('password_protected', 'encrypted', 'corrupt_structure', 'mime_mismatch', 'empty_source', 'size_limit_exceeded')))");
            DB::statement("ALTER TABLE import_preflight_attempts ADD CONSTRAINT import_preflight_attempts_status_check CHECK (status IN ('open', 'completed', 'failed', 'expired'))");
            DB::statement("ALTER TABLE import_preflight_attempts ADD CONSTRAINT import_preflight_attempts_result_check CHECK ((status = 'open' AND result IS NULL AND diagnostic_code IS NULL AND reported_payload_sha256 IS NULL AND completed_at IS NULL) OR (status = 'completed' AND result IN ('readable', 'password_protected', 'encrypted', 'corrupt_structure', 'mime_mismatch') AND diagnostic_code IS NOT NULL AND reported_payload_sha256 ~ '^[0-9a-f]{64}$' AND completed_at IS NOT NULL) OR (status = 'failed' AND result IS NULL AND diagnostic_code IN ('source_unavailable', 'read_timeout', 'internal_failure') AND reported_payload_sha256 ~ '^[0-9a-f]{64}$' AND completed_at IS NOT NULL) OR (status = 'expired' AND result IS NULL AND diagnostic_code = 'lease_expired' AND reported_payload_sha256 IS NULL AND completed_at IS NOT NULL))");
            DB::statement("ALTER TABLE import_preflight_attempts ADD CONSTRAINT import_preflight_attempts_lease_check CHECK (lease_generation > 0 AND lease_token_hash ~ '^[0-9a-f]{64}$')");
            DB::statement("ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_subject_check CHECK ((event_type IN ('document.ingestion.requested', 'document.deletion.requested') AND document_public_id IS NOT NULL AND import_item_public_id IS NULL) OR (event_type = 'import.preflight.requested' AND document_public_id IS NULL AND import_item_public_id IS NOT NULL))");
            DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_import_preflight_attempt_update() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.event_id <> OLD.event_id
        OR NEW.import_item_id <> OLD.import_item_id
        OR NEW.workspace_id <> OLD.workspace_id
        OR NEW.lease_generation <> OLD.lease_generation
        OR NEW.lease_token_hash <> OLD.lease_token_hash
        OR NEW.lease_expires_at <> OLD.lease_expires_at
        OR NEW.staged_object_key <> OLD.staged_object_key
        OR NEW.declared_media_type <> OLD.declared_media_type THEN
        RAISE EXCEPTION 'import preflight attempt identity and lease are immutable';
    END IF;
    IF OLD.status <> 'open' AND ROW(NEW.status, NEW.result, NEW.diagnostic_code, NEW.reported_payload_sha256, NEW.completed_at)
        IS DISTINCT FROM ROW(OLD.status, OLD.result, OLD.diagnostic_code, OLD.reported_payload_sha256, OLD.completed_at) THEN
        RAISE EXCEPTION 'terminal import preflight attempts are immutable';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER import_preflight_attempts_update_guard
BEFORE UPDATE ON import_preflight_attempts
FOR EACH ROW EXECUTE FUNCTION guard_import_preflight_attempt_update();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS guard_import_preflight_attempt_update() CASCADE');
            DB::statement('ALTER TABLE outbox_events DROP CONSTRAINT IF EXISTS outbox_events_subject_check');
            DB::statement('ALTER TABLE import_items DROP CONSTRAINT IF EXISTS import_items_preflight_result_check');
            DB::statement("ALTER TABLE import_items ADD CONSTRAINT import_items_preflight_result_check CHECK ((preflight_status = 'pending' AND source_checksum_sha256 IS NULL AND media_type IS NULL AND size_bytes IS NULL AND preflight_rejection_reason IS NULL) OR (preflight_status = 'verified' AND source_checksum_sha256 ~ '^[0-9a-f]{64}$' AND media_type IS NOT NULL AND size_bytes IS NOT NULL AND preflight_rejection_reason IS NULL) OR (preflight_status = 'rejected' AND preflight_rejection_reason IN ('password_protected', 'encrypted', 'corrupt_structure', 'mime_mismatch')))");
        }
        DB::table('outbox_events')->where('event_type', 'import.preflight.requested')->delete();
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->dropColumn('import_item_public_id');
            $table->uuid('document_public_id')->nullable(false)->change();
        });
        DB::statement('DROP INDEX IF EXISTS import_preflight_attempts_one_open_per_item_unique');
        Schema::dropIfExists('import_preflight_attempts');
        Schema::table('import_items', function (Blueprint $table): void {
            $table->dropColumn('declared_media_type');
        });
    }
};
