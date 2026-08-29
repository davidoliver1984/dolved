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
        Schema::table('document_families', function (Blueprint $table): void {
            $table->timestampTz('tombstoned_at')->nullable()->after('review_due_date');
        });

        Schema::create('document_family_deletion_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('document_family_id');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->string('status');
            $table->char('confirmation_state_digest', 64);
            $table->json('version_snapshot');
            $table->unsignedInteger('child_count');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['document_family_id', 'workspace_id'],
                'family_deletions_family_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('document_families')
                ->restrictOnDelete();
            $table->unique(
                ['document_family_id', 'idempotency_key'],
                'family_deletions_family_idempotency_unique',
            );
            $table->index(['workspace_id', 'status']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX family_deletions_open_family_unique
             ON document_family_deletion_operations (document_family_id)
             WHERE status IN ('pending', 'processing', 'partially_failed')"
        );

        Schema::table('document_deletion_operations', function (Blueprint $table): void {
            $table->foreignId('document_family_deletion_operation_id')
                ->nullable()
                ->after('document_id')
                ->constrained('document_family_deletion_operations')
                ->restrictOnDelete();
            $table->unique(
                ['document_family_deletion_operation_id', 'document_id'],
                'document_deletions_family_parent_document_unique',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE document_family_deletion_operations ADD CONSTRAINT family_deletion_status_check CHECK (status IN ('pending', 'processing', 'completed', 'partially_failed'))");
            DB::statement("ALTER TABLE document_family_deletion_operations ADD CONSTRAINT family_deletion_digest_check CHECK (confirmation_state_digest ~ '^[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::table('document_deletion_operations', function (Blueprint $table): void {
            $table->dropUnique('document_deletions_family_parent_document_unique');
            $table->dropConstrainedForeignId('document_family_deletion_operation_id');
        });
        DB::statement('DROP INDEX IF EXISTS family_deletions_open_family_unique');
        Schema::dropIfExists('document_family_deletion_operations');
        Schema::table('document_families', function (Blueprint $table): void {
            $table->dropColumn('tombstoned_at');
        });
    }
};
