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
        Schema::create('document_families', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['id', 'workspace_id'], 'document_families_id_workspace_unique');
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('organisational_locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('kind')->nullable();
            $table->timestamps();

            $table->unique(['id', 'workspace_id'], 'organisational_locations_id_workspace_unique');
            $table->foreign(
                ['parent_id', 'workspace_id'],
                'organisational_locations_parent_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('organisational_locations')
                ->restrictOnDelete();
            $table->index(['workspace_id', 'name']);
            $table->index(['workspace_id', 'parent_id']);
        });

        Schema::create('organisational_location_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('organisational_location_id');
            $table->string('alias');
            $table->string('normalised_alias');
            $table->timestamps();

            $table->foreign(
                ['organisational_location_id', 'workspace_id'],
                'organisational_location_aliases_location_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('organisational_locations')
                ->cascadeOnDelete();
            $table->unique(
                ['organisational_location_id', 'normalised_alias'],
                'organisational_location_aliases_location_alias_unique',
            );
            $table->index(['workspace_id', 'normalised_alias']);
        });

        Schema::create('document_family_default_applicabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('document_family_id');
            $table->unsignedBigInteger('organisational_location_id');
            $table->timestamps();

            $table->foreign(
                ['document_family_id', 'workspace_id'],
                'document_family_defaults_family_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('document_families')
                ->cascadeOnDelete();
            $table->foreign(
                ['organisational_location_id', 'workspace_id'],
                'document_family_defaults_location_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('organisational_locations')
                ->restrictOnDelete();
            $table->unique(
                ['document_family_id', 'organisational_location_id'],
                'document_family_defaults_family_location_unique',
            );
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('document_family_id')->nullable()->after('workspace_id');
            $table->unsignedBigInteger('predecessor_document_id')->nullable()->after('document_family_id');
            $table->enum('governance_status', ['draft', 'approved', 'withdrawn'])
                ->default('draft')
                ->after('status');
            $table->timestampTz('effective_from')->nullable()->after('governance_status');
            $table->timestampTz('approved_at')->nullable()->after('effective_from');
            $table->timestampTz('withdrawn_at')->nullable()->after('approved_at');
        });

        DB::table('documents')->orderBy('id')->each(function (object $document): void {
            $familyId = DB::table('document_families')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $document->workspace_id,
                'name' => $document->source_filename,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
            ]);

            DB::table('documents')->where('id', $document->id)->update([
                'document_family_id' => $familyId,
                'governance_status' => 'draft',
                'effective_from' => $document->created_at,
            ]);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('document_family_id')->nullable(false)->change();
            $table->timestampTz('effective_from')->nullable(false)->change();
            $table->unique(
                ['id', 'workspace_id', 'document_family_id'],
                'documents_id_workspace_family_unique',
            );
            $table->foreign(
                ['document_family_id', 'workspace_id'],
                'documents_family_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('document_families')
                ->restrictOnDelete();
            $table->foreign(
                ['predecessor_document_id', 'workspace_id', 'document_family_id'],
                'documents_predecessor_workspace_family_foreign',
            )->references(['id', 'workspace_id', 'document_family_id'])
                ->on('documents')
                ->restrictOnDelete();
            $table->index(['document_family_id', 'effective_from']);
            $table->index(['workspace_id', 'governance_status', 'effective_from']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX documents_one_root_per_family
             ON documents (document_family_id)
             WHERE predecessor_document_id IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX documents_one_successor_per_predecessor
             ON documents (predecessor_document_id)
             WHERE predecessor_document_id IS NOT NULL'
        );

        DB::statement(
            "CREATE UNIQUE INDEX documents_approved_effective_from_unique
             ON documents (document_family_id, effective_from)
             WHERE governance_status IN ('approved', 'withdrawn')"
        );

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX documents_authority_start_unique
                 ON documents (
                    document_family_id,
                    GREATEST(effective_from, approved_at)
                 )
                 WHERE approved_at IS NOT NULL'
            );
        }

        Schema::create('document_applicability_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('document_id');
            $table->enum('scope', ['universal', 'specific'])->default('universal');
            $table->timestampTz('sealed_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['document_id', 'workspace_id'],
                'document_applicability_snapshots_document_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('documents')
                ->cascadeOnDelete();
            $table->unique('document_id');
            $table->unique(
                ['id', 'workspace_id'],
                'document_applicability_snapshots_id_workspace_unique',
            );
        });

        Schema::create('document_applicability_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('document_applicability_snapshot_id');
            $table->unsignedBigInteger('organisational_location_id');
            $table->timestamps();

            $table->foreign(
                ['document_applicability_snapshot_id', 'workspace_id'],
                'document_applicability_locations_snapshot_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('document_applicability_snapshots')
                ->cascadeOnDelete();
            $table->foreign(
                ['organisational_location_id', 'workspace_id'],
                'document_applicability_locations_location_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('organisational_locations')
                ->restrictOnDelete();
            $table->unique(
                ['document_applicability_snapshot_id', 'organisational_location_id'],
                'document_applicability_locations_snapshot_location_unique',
            );
        });

        DB::table('documents')->orderBy('id')->each(function (object $document): void {
            DB::table('document_applicability_snapshots')->insert([
                'workspace_id' => $document->workspace_id,
                'document_id' => $document->id,
                'scope' => 'universal',
                'sealed_at' => $document->created_at,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
            ]);
        });

        Schema::create('document_governance_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('document_id');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('previous_values');
            $table->json('new_values');
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->foreign(
                ['document_id', 'workspace_id'],
                'document_governance_audits_document_workspace_foreign',
            )->references(['id', 'workspace_id'])
                ->on('documents')
                ->restrictOnDelete();
            $table->index(['workspace_id', 'document_id', 'occurred_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->createPostgresConstraints();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS document_applicability_snapshot_seal_guard
                ON document_applicability_snapshots;
                DROP FUNCTION IF EXISTS enforce_document_applicability_snapshot_seal();
                DROP TRIGGER IF EXISTS document_applicability_location_immutability
                ON document_applicability_locations;
                DROP FUNCTION IF EXISTS enforce_document_applicability_location_immutability();
                DROP TRIGGER IF EXISTS organisational_location_hierarchy_guard
                ON organisational_locations;
                DROP FUNCTION IF EXISTS enforce_organisational_location_hierarchy();
                DROP TRIGGER IF EXISTS document_lineage_guard ON documents;
                DROP FUNCTION IF EXISTS enforce_document_lineage();
                SQL);
        }

        Schema::dropIfExists('document_governance_audit_events');
        Schema::dropIfExists('document_applicability_locations');
        Schema::dropIfExists('document_applicability_snapshots');

        DB::statement('DROP INDEX IF EXISTS documents_authority_start_unique');
        DB::statement('DROP INDEX IF EXISTS documents_approved_effective_from_unique');
        DB::statement('DROP INDEX IF EXISTS documents_one_successor_per_predecessor');
        DB::statement('DROP INDEX IF EXISTS documents_one_root_per_family');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign('documents_predecessor_workspace_family_foreign');
            $table->dropForeign('documents_family_workspace_foreign');
            $table->dropUnique('documents_id_workspace_family_unique');
            $table->dropIndex('documents_document_family_id_effective_from_index');
            $table->dropIndex('documents_workspace_id_governance_status_effective_from_index');
            $table->dropColumn([
                'document_family_id',
                'predecessor_document_id',
                'governance_status',
                'effective_from',
                'approved_at',
                'withdrawn_at',
            ]);
        });

        Schema::dropIfExists('document_family_default_applicabilities');
        Schema::dropIfExists('organisational_location_aliases');
        Schema::dropIfExists('organisational_locations');
        Schema::dropIfExists('document_families');
    }

    private function createPostgresConstraints(): void
    {
        DB::statement(
            "ALTER TABLE documents
             ADD CONSTRAINT documents_governance_timestamps_consistent
             CHECK (
                (governance_status = 'draft'
                    AND approved_at IS NULL
                    AND withdrawn_at IS NULL)
                OR (governance_status = 'approved'
                    AND approved_at IS NOT NULL
                    AND withdrawn_at IS NULL)
                OR (governance_status = 'withdrawn'
                    AND approved_at IS NOT NULL
                    AND withdrawn_at IS NOT NULL
                    AND withdrawn_at >= approved_at)
             )"
        );

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION enforce_document_lineage()
            RETURNS trigger AS $$
            DECLARE
                predecessor_effective_from timestamptz;
            BEGIN
                IF TG_OP = 'UPDATE' AND (
                    NEW.document_family_id <> OLD.document_family_id
                    OR NEW.predecessor_document_id IS DISTINCT FROM OLD.predecessor_document_id
                ) THEN
                    RAISE EXCEPTION 'document family and predecessor are immutable';
                END IF;

                IF NEW.predecessor_document_id IS NOT NULL THEN
                    SELECT effective_from
                    INTO predecessor_effective_from
                    FROM documents
                    WHERE id = NEW.predecessor_document_id
                      AND workspace_id = NEW.workspace_id
                      AND document_family_id = NEW.document_family_id;

                    IF predecessor_effective_from IS NULL THEN
                        RAISE EXCEPTION 'document predecessor must belong to the same workspace and family';
                    END IF;

                    IF predecessor_effective_from >= NEW.effective_from THEN
                        RAISE EXCEPTION 'document predecessor must have an earlier effective date';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER document_lineage_guard
            BEFORE INSERT OR UPDATE OF document_family_id, predecessor_document_id, effective_from
            ON documents
            FOR EACH ROW
            EXECUTE FUNCTION enforce_document_lineage();

            CREATE FUNCTION enforce_organisational_location_hierarchy()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.parent_id IS NULL THEN
                    RETURN NEW;
                END IF;

                IF NEW.parent_id = NEW.id THEN
                    RAISE EXCEPTION 'an organisational location cannot be its own parent';
                END IF;

                IF EXISTS (
                    WITH RECURSIVE ancestors AS (
                        SELECT parent_id
                        FROM organisational_locations
                        WHERE id = NEW.parent_id
                          AND workspace_id = NEW.workspace_id
                        UNION ALL
                        SELECT location.parent_id
                        FROM organisational_locations location
                        JOIN ancestors ON location.id = ancestors.parent_id
                        WHERE location.workspace_id = NEW.workspace_id
                    )
                    SELECT 1 FROM ancestors WHERE parent_id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'an organisational location hierarchy cannot contain a cycle';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER organisational_location_hierarchy_guard
            BEFORE INSERT OR UPDATE OF parent_id, workspace_id
            ON organisational_locations
            FOR EACH ROW
            EXECUTE FUNCTION enforce_organisational_location_hierarchy();

            CREATE FUNCTION enforce_document_applicability_location_immutability()
            RETURNS trigger AS $$
            DECLARE
                snapshot_id bigint;
                snapshot_sealed_at timestamptz;
            BEGIN
                snapshot_id := COALESCE(
                    NEW.document_applicability_snapshot_id,
                    OLD.document_applicability_snapshot_id
                );

                SELECT sealed_at
                INTO snapshot_sealed_at
                FROM document_applicability_snapshots
                WHERE id = snapshot_id;

                IF snapshot_sealed_at IS NOT NULL THEN
                    RAISE EXCEPTION 'a sealed document applicability snapshot is immutable';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER document_applicability_location_immutability
            BEFORE INSERT OR UPDATE OR DELETE
            ON document_applicability_locations
            FOR EACH ROW
            EXECUTE FUNCTION enforce_document_applicability_location_immutability();

            CREATE FUNCTION enforce_document_applicability_snapshot_seal()
            RETURNS trigger AS $$
            DECLARE
                location_count integer;
            BEGIN
                IF TG_OP = 'UPDATE' AND OLD.sealed_at IS NOT NULL AND (
                    NEW.scope <> OLD.scope
                    OR NEW.sealed_at IS DISTINCT FROM OLD.sealed_at
                ) THEN
                    RAISE EXCEPTION 'a sealed document applicability snapshot is immutable';
                END IF;

                IF NEW.sealed_at IS NOT NULL AND (
                    TG_OP = 'INSERT'
                    OR OLD.sealed_at IS NULL
                ) THEN
                    SELECT COUNT(*)
                    INTO location_count
                    FROM document_applicability_locations
                    WHERE document_applicability_snapshot_id = NEW.id;

                    IF NEW.scope = 'universal' AND location_count <> 0 THEN
                        RAISE EXCEPTION 'a universal applicability snapshot cannot contain locations';
                    END IF;

                    IF NEW.scope = 'specific' AND location_count = 0 THEN
                        RAISE EXCEPTION 'a specific applicability snapshot requires at least one location';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER document_applicability_snapshot_seal_guard
            BEFORE INSERT OR UPDATE
            ON document_applicability_snapshots
            FOR EACH ROW
            EXECUTE FUNCTION enforce_document_applicability_snapshot_seal();
            SQL);
    }
};
