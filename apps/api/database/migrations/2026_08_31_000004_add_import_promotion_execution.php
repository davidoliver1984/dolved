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
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('storage_version_id')->nullable()->after('storage_key');
        });
        Schema::table('promotion_attempts', function (Blueprint $table): void {
            $table->string('terminal_reason')->nullable()->after('status');
            $table->unsignedBigInteger('committed_document_id')->nullable()->after('checksum_evidence');
            $table->foreign(
                ['committed_document_id', 'workspace_id'],
                'promotion_attempts_committed_document_workspace_foreign',
            )->references(['id', 'workspace_id'])->on('documents')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE promotion_attempts ADD CONSTRAINT promotion_attempts_terminal_result_check CHECK ((status = 'committed' AND committed_document_id IS NOT NULL AND terminal_reason IS NULL) OR (status = 'conflict' AND committed_document_id IS NULL AND terminal_reason IN ('duplicate', 'invalidated_predecessor', 'authorization_changed', 'decision_changed')) OR (status = 'failed' AND committed_document_id IS NULL AND terminal_reason = 'technical_exhaustion') OR (status = 'abandoned' AND committed_document_id IS NULL AND terminal_reason = 'cancelled') OR (status = 'expired' AND committed_document_id IS NULL AND terminal_reason = 'retention_expired') OR (status IN ('reserved', 'copying', 'source_verified') AND committed_document_id IS NULL AND terminal_reason IS NULL))");
            DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_document_storage_version_update() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.storage_version_id IS DISTINCT FROM OLD.storage_version_id THEN
        RAISE EXCEPTION 'document storage version identity is immutable';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER documents_storage_version_update_guard
BEFORE UPDATE ON documents
FOR EACH ROW EXECUTE FUNCTION guard_document_storage_version_update();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS guard_document_storage_version_update() CASCADE');
            DB::statement('ALTER TABLE promotion_attempts DROP CONSTRAINT IF EXISTS promotion_attempts_terminal_result_check');
            DB::statement('ALTER TABLE promotion_attempts DROP CONSTRAINT IF EXISTS promotion_attempts_committed_document_workspace_foreign');
            DB::statement('ALTER TABLE promotion_attempts DROP CONSTRAINT IF EXISTS promotion_attempts_committed_document_id_foreign');
        } else {
            Schema::table('promotion_attempts', function (Blueprint $table): void {
                $table->dropForeign('promotion_attempts_committed_document_workspace_foreign');
            });
        }
        Schema::table('promotion_attempts', function (Blueprint $table): void {
            $table->dropColumn('committed_document_id');
            $table->dropColumn('terminal_reason');
        });
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('storage_version_id');
        });
    }
};
