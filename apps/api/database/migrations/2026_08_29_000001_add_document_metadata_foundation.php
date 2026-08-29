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
        Schema::create('document_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('normalised_name', 100);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['id', 'workspace_id'], 'document_categories_id_workspace_unique');
            $table->unique(['workspace_id', 'normalised_name'], 'document_categories_workspace_name_unique');
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('document_tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('name', 64);
            $table->string('normalised_name', 64);
            $table->timestamps();

            $table->unique(['id', 'workspace_id'], 'document_tags_id_workspace_unique');
            $table->unique(['workspace_id', 'normalised_name'], 'document_tags_workspace_name_unique');
        });

        Schema::table('document_families', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
            $table->unsignedBigInteger('category_id')->nullable()->after('description');
            // Transitional until the separately reviewed R23-S01c owner backfill.
            $table->foreignId('owner_user_id')->nullable()->after('category_id')
                ->constrained('users')->restrictOnDelete();
            $table->date('review_due_date')->nullable()->after('owner_user_id');

            $table->foreign(
                ['category_id', 'workspace_id'],
                'document_families_category_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('document_categories')
                ->restrictOnDelete();
            $table->index(['workspace_id', 'category_id']);
            $table->index(['workspace_id', 'owner_user_id']);
            $table->index(['workspace_id', 'review_due_date']);
        });

        Schema::create('document_family_tag_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('document_family_id');
            $table->unsignedBigInteger('document_tag_id');
            $table->timestamps();

            $table->foreign(
                ['document_family_id', 'workspace_id'],
                'document_family_tags_family_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('document_families')
                ->cascadeOnDelete();
            $table->foreign(
                ['document_tag_id', 'workspace_id'],
                'document_family_tags_tag_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('document_tags')
                ->restrictOnDelete();
            $table->unique(
                ['document_family_id', 'document_tag_id'],
                'document_family_tags_family_tag_unique',
            );
            $table->index(['workspace_id', 'document_tag_id']);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->string('publisher_label')->nullable()->after('source_filename');
            $table->string('source_url', 2048)->nullable()->after('publisher_label');
            $table->char('source_checksum_sha256', 64)->nullable()->after('size_bytes');
            $table->string('checksum_verification_status', 16)
                ->default('pending')
                ->after('source_checksum_sha256');
            $table->string('checksum_unavailable_reason', 32)
                ->nullable()
                ->after('checksum_verification_status');
            $table->index(['workspace_id', 'checksum_verification_status']);
        });

        Schema::table('document_governance_audit_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('document_family_id')->nullable()->after('workspace_id');
            $table->string('target_scope', 16)->default('version')->after('document_id');
            $table->string('actor_type', 16)->default('human')->after('target_scope');
            $table->string('system_actor_code', 64)->nullable()->after('actor_user_id');
        });

        // Existing events are all human, version-scoped events and already
        // point at a valid document; this value-preserving shape transition is
        // required before the final relational constraints can be installed.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                UPDATE document_governance_audit_events
                SET document_family_id = (
                    SELECT documents.document_family_id
                    FROM documents
                    WHERE documents.id = document_governance_audit_events.document_id
                ),
                target_scope = 'version',
                actor_type = 'human'
                SQL);
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE document_governance_audit_events AS audit
                SET document_family_id = documents.document_family_id,
                    target_scope = 'version',
                    actor_type = 'human'
                FROM documents
                WHERE documents.id = audit.document_id
                SQL);
        } else {
            DB::table('document_governance_audit_events')
                ->join('documents', 'documents.id', '=', 'document_governance_audit_events.document_id')
                ->update([
                    'document_family_id' => DB::raw('documents.document_family_id'),
                    'target_scope' => 'version',
                    'actor_type' => 'human',
                ]);
        }

        Schema::table('document_governance_audit_events', function (Blueprint $table): void {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['document_id', 'workspace_id']);
            } else {
                $table->dropForeign('document_governance_audits_document_workspace_foreign');
            }
            $table->foreignId('actor_user_id')->nullable()->change();
            $table->unsignedBigInteger('document_id')->nullable()->change();
            $table->unsignedBigInteger('document_family_id')->nullable(false)->change();

            $table->foreign(
                ['document_family_id', 'workspace_id'],
                'document_governance_audits_family_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('document_families')
                ->restrictOnDelete();
            $table->foreign(
                ['document_id', 'workspace_id', 'document_family_id'],
                'document_governance_audits_document_workspace_family_foreign',
            )->references(['id', 'workspace_id', 'document_family_id'])
                ->on('documents')
                ->restrictOnDelete();
            $table->index(
                ['workspace_id', 'document_family_id', 'occurred_at'],
                'document_governance_audits_workspace_family_occurred_index',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresConstraints();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE document_governance_audit_events DROP CONSTRAINT IF EXISTS document_governance_audits_target_shape_check');
            DB::statement('ALTER TABLE document_governance_audit_events DROP CONSTRAINT IF EXISTS document_governance_audits_actor_shape_check');
            DB::statement('ALTER TABLE document_governance_audit_events DROP CONSTRAINT IF EXISTS document_governance_audits_system_actor_code_check');
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_checksum_shape_check');
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_checksum_unavailable_reason_check');
            DB::statement('ALTER TABLE document_categories DROP CONSTRAINT IF EXISTS document_categories_status_check');
        }

        Schema::table('document_governance_audit_events', function (Blueprint $table): void {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['document_id', 'workspace_id', 'document_family_id']);
                $table->dropForeign(['document_family_id', 'workspace_id']);
            } else {
                $table->dropForeign('document_governance_audits_document_workspace_family_foreign');
                $table->dropForeign('document_governance_audits_family_workspace_foreign');
            }
            $table->dropIndex('document_governance_audits_workspace_family_occurred_index');
            $table->dropColumn(['document_family_id', 'target_scope', 'actor_type', 'system_actor_code']);
            $table->foreign(
                ['document_id', 'workspace_id'],
                'document_governance_audits_document_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('documents')
                ->restrictOnDelete();
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_workspace_id_checksum_verification_status_index');
            $table->dropColumn([
                'publisher_label',
                'source_url',
                'source_checksum_sha256',
                'checksum_verification_status',
                'checksum_unavailable_reason',
            ]);
        });

        Schema::dropIfExists('document_family_tag_assignments');

        Schema::table('document_families', function (Blueprint $table): void {
            $table->dropForeign('document_families_category_workspace_foreign');
            $table->dropForeign(['owner_user_id']);
            $table->dropIndex('document_families_workspace_id_category_id_index');
            $table->dropIndex('document_families_workspace_id_owner_user_id_index');
            $table->dropIndex('document_families_workspace_id_review_due_date_index');
            $table->dropColumn(['description', 'category_id', 'owner_user_id', 'review_due_date']);
        });

        Schema::dropIfExists('document_tags');
        Schema::dropIfExists('document_categories');
    }

    private function createPostgresConstraints(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE document_categories
            ADD CONSTRAINT document_categories_status_check
            CHECK (status IN ('active', 'archived'))
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE documents
            ADD CONSTRAINT documents_checksum_shape_check
            CHECK (
                (
                    checksum_verification_status = 'verified'
                    AND source_checksum_sha256 IS NOT NULL
                    AND checksum_unavailable_reason IS NULL
                )
                OR (
                    checksum_verification_status = 'pending'
                    AND source_checksum_sha256 IS NULL
                    AND checksum_unavailable_reason IS NULL
                )
                OR (
                    checksum_verification_status = 'unavailable'
                    AND source_checksum_sha256 IS NULL
                    AND checksum_unavailable_reason IS NOT NULL
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE documents
            ADD CONSTRAINT documents_checksum_unavailable_reason_check
            CHECK (
                checksum_unavailable_reason IS NULL
                OR checksum_unavailable_reason IN (
                    'source_missing',
                    'source_deleted',
                    'source_unrecoverable'
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE document_governance_audit_events
            ADD CONSTRAINT document_governance_audits_target_shape_check
            CHECK (
                (target_scope = 'family' AND document_id IS NULL)
                OR (target_scope = 'version' AND document_id IS NOT NULL)
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE document_governance_audit_events
            ADD CONSTRAINT document_governance_audits_actor_shape_check
            CHECK (
                (
                    actor_type = 'human'
                    AND actor_user_id IS NOT NULL
                    AND system_actor_code IS NULL
                )
                OR (
                    actor_type = 'system'
                    AND actor_user_id IS NULL
                    AND system_actor_code IS NOT NULL
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE document_governance_audit_events
            ADD CONSTRAINT document_governance_audits_system_actor_code_check
            CHECK (
                system_actor_code IS NULL
                OR system_actor_code IN (
                    'owner_backfill_lineage_root',
                    'owner_backfill_workspace_creator_fallback',
                    'checksum_backfill',
                    'audit_target_scope_backfill'
                )
            )
            SQL);
    }
};
