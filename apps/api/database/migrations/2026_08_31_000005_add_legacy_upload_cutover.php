<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_upload_initialization_gate', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('cutover_operation_id')->unique();
            $table->boolean('closed')->default(false);
            $table->unsignedBigInteger('inventory_cursor_id')->default(0);
            $table->unsignedBigInteger('total_marked_count')->default(0);
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('drain_closed_at')->nullable();
            $table->timestampsTz();
        });
        DB::table('legacy_upload_initialization_gate')->insert([
            'id' => 1,
            'cutover_operation_id' => (string) Str::uuid(),
            'closed' => false,
            'inventory_cursor_id' => 0,
            'total_marked_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('legacy_upload_initiated_before_cutover')->nullable()->after('storage_version_id');
            $table->uuid('legacy_upload_cutover_operation_id')->nullable()->after('legacy_upload_initiated_before_cutover');
            $table->index(['legacy_upload_initiated_before_cutover', 'status'], 'documents_legacy_cutover_drain_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_legacy_cutover_operation_foreign FOREIGN KEY (legacy_upload_cutover_operation_id) REFERENCES legacy_upload_initialization_gate (cutover_operation_id) ON DELETE RESTRICT');
        }

        Schema::create('legacy_upload_cutover_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('cutover_operation_id');
            $table->unsignedBigInteger('document_id')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('actor_type', 16);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('system_actor_code', 64)->nullable();
            $table->string('reason', 32);
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
            $table->foreign('cutover_operation_id')->references('cutover_operation_id')->on('legacy_upload_initialization_gate')->restrictOnDelete();
            $table->foreign(['document_id', 'workspace_id'], 'legacy_cutover_audits_document_workspace_foreign')
                ->references(['id', 'workspace_id'])->on('documents')->restrictOnDelete();
        });

        Schema::create('legacy_upload_cutover_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('cutover_operation_id');
            $table->string('event_type', 32);
            $table->unsignedBigInteger('total_marked_count');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
            $table->foreign('cutover_operation_id')->references('cutover_operation_id')->on('legacy_upload_initialization_gate')->restrictOnDelete();
            $table->unique(['cutover_operation_id', 'event_type'], 'legacy_cutover_events_operation_type_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE legacy_upload_initialization_gate ADD CONSTRAINT legacy_upload_gate_singleton_check CHECK (id = 1)');
            DB::statement('ALTER TABLE legacy_upload_initialization_gate ADD CONSTRAINT legacy_upload_gate_state_check CHECK ((closed = false AND closed_at IS NULL AND drain_closed_at IS NULL) OR (closed = true AND closed_at IS NOT NULL AND (drain_closed_at IS NULL OR drain_closed_at >= closed_at)))');
            DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_legacy_cutover_marker_check CHECK ((legacy_upload_initiated_before_cutover IS NULL AND legacy_upload_cutover_operation_id IS NULL) OR (legacy_upload_initiated_before_cutover = true AND legacy_upload_cutover_operation_id IS NOT NULL))');
            DB::statement("ALTER TABLE legacy_upload_cutover_audits ADD CONSTRAINT legacy_cutover_audits_actor_check CHECK ((actor_type = 'human' AND actor_user_id IS NOT NULL AND system_actor_code IS NULL) OR (actor_type = 'system' AND actor_user_id IS NULL AND system_actor_code = 'legacy_upload_cutover'))");
            DB::statement("ALTER TABLE legacy_upload_cutover_audits ADD CONSTRAINT legacy_cutover_audits_reason_check CHECK (reason IN ('transition_window_creation', 'inventory_backfill', 'final_remainder'))");
            DB::statement("ALTER TABLE legacy_upload_cutover_events ADD CONSTRAINT legacy_cutover_events_type_check CHECK (event_type IN ('gate_closed', 'drain_closed'))");
            DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_legacy_upload_cutover_identity() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.legacy_upload_cutover_operation_id IS NOT NULL AND
       (NEW.legacy_upload_cutover_operation_id IS DISTINCT FROM OLD.legacy_upload_cutover_operation_id OR
        NEW.legacy_upload_initiated_before_cutover IS DISTINCT FROM OLD.legacy_upload_initiated_before_cutover) THEN
        RAISE EXCEPTION 'legacy upload cutover identity is immutable';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER documents_legacy_cutover_identity_guard
BEFORE UPDATE ON documents
FOR EACH ROW EXECUTE FUNCTION guard_legacy_upload_cutover_identity();

CREATE FUNCTION guard_legacy_upload_gate_progress() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.cutover_operation_id IS DISTINCT FROM OLD.cutover_operation_id OR
       (OLD.closed AND NOT NEW.closed) OR
       (OLD.drain_closed_at IS NOT NULL AND NEW.drain_closed_at IS DISTINCT FROM OLD.drain_closed_at) THEN
        RAISE EXCEPTION 'legacy upload gate identity and closure are forward only';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER legacy_upload_gate_progress_guard
BEFORE UPDATE ON legacy_upload_initialization_gate
FOR EACH ROW EXECUTE FUNCTION guard_legacy_upload_gate_progress();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS guard_legacy_upload_gate_progress() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS guard_legacy_upload_cutover_identity() CASCADE');
        }
        Schema::dropIfExists('legacy_upload_cutover_events');
        Schema::dropIfExists('legacy_upload_cutover_audits');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_legacy_cutover_operation_foreign');
        }
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_legacy_cutover_drain_index');
            $table->dropColumn(['legacy_upload_initiated_before_cutover', 'legacy_upload_cutover_operation_id']);
        });
        Schema::dropIfExists('legacy_upload_initialization_gate');
    }
};
