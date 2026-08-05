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
        Schema::create('workspace_corpus_generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('embedding_space_generation_id')
                ->constrained()
                ->restrictOnDelete();
            $table->enum('status', [
                'building',
                'verifying',
                'active',
                'superseded',
                'retired',
            ])->default('building');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['id', 'workspace_id'],
                'workspace_corpus_generations_id_workspace_unique'
            );
            $table->index(
                ['workspace_id', 'status'],
                'workspace_corpus_generations_workspace_status_index'
            );
            $table->index(
                ['embedding_space_generation_id', 'status'],
                'workspace_corpus_generations_embedding_status_index'
            );
        });

        DB::statement(
            "CREATE UNIQUE INDEX workspace_corpus_generations_one_active_per_workspace
            ON workspace_corpus_generations (workspace_id)
            WHERE status = 'active'"
        );

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_workspace_corpus_generation_id')
                ->nullable();
            $table->foreign(
                ['active_workspace_corpus_generation_id', 'id'],
                'workspaces_active_corpus_workspace_foreign'
            )
                ->references(['id', 'workspace_id'])
                ->on('workspace_corpus_generations')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE workspace_corpus_generations
                ADD CONSTRAINT workspace_corpus_generations_lifecycle_timestamps
                CHECK (
                    (status IN ('building', 'verifying')
                        AND activated_at IS NULL
                        AND superseded_at IS NULL
                        AND retired_at IS NULL)
                    OR (status = 'active'
                        AND activated_at IS NOT NULL
                        AND superseded_at IS NULL
                        AND retired_at IS NULL)
                    OR (status = 'superseded'
                        AND activated_at IS NOT NULL
                        AND superseded_at IS NOT NULL
                        AND retired_at IS NULL)
                    OR (status = 'retired' AND retired_at IS NOT NULL)
                )"
            );
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION enforce_active_corpus_available_embedding_space()
                RETURNS trigger AS $$
                BEGIN
                    IF NEW.status = 'active' THEN
                        PERFORM 1
                        FROM embedding_space_generations
                        WHERE id = NEW.embedding_space_generation_id
                          AND status = 'available'
                        FOR KEY SHARE;

                        IF NOT FOUND THEN
                            RAISE EXCEPTION 'an active corpus generation requires an available embedding space';
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER active_corpus_available_embedding_space
                BEFORE INSERT OR UPDATE OF status, embedding_space_generation_id
                ON workspace_corpus_generations
                FOR EACH ROW
                EXECUTE FUNCTION enforce_active_corpus_available_embedding_space();

                CREATE FUNCTION prevent_retiring_referenced_embedding_space()
                RETURNS trigger AS $$
                BEGIN
                    IF OLD.status = 'available'
                       AND NEW.status <> 'available'
                       AND EXISTS (
                           SELECT 1
                           FROM workspace_corpus_generations
                           WHERE embedding_space_generation_id = NEW.id
                             AND status = 'active'
                       ) THEN
                        RAISE EXCEPTION 'an embedding space with active corpus generations must remain available';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER referenced_embedding_space_remains_available
                BEFORE UPDATE OF status
                ON embedding_space_generations
                FOR EACH ROW
                EXECUTE FUNCTION prevent_retiring_referenced_embedding_space();

                CREATE FUNCTION enforce_workspace_active_corpus_pointer()
                RETURNS trigger AS $$
                BEGIN
                    IF NEW.active_workspace_corpus_generation_id IS NOT NULL
                       AND NOT EXISTS (
                           SELECT 1
                           FROM workspace_corpus_generations
                           WHERE id = NEW.active_workspace_corpus_generation_id
                             AND workspace_id = NEW.id
                             AND status = 'active'
                       ) THEN
                        RAISE EXCEPTION 'a workspace active corpus pointer must reference its active generation';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER workspace_active_corpus_pointer
                BEFORE INSERT OR UPDATE OF active_workspace_corpus_generation_id
                ON workspaces
                FOR EACH ROW
                EXECUTE FUNCTION enforce_workspace_active_corpus_pointer();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS referenced_embedding_space_remains_available
                ON embedding_space_generations;
                DROP FUNCTION IF EXISTS prevent_retiring_referenced_embedding_space();
                DROP TRIGGER IF EXISTS workspace_active_corpus_pointer
                ON workspaces;
                DROP FUNCTION IF EXISTS enforce_workspace_active_corpus_pointer();
                DROP TRIGGER IF EXISTS active_corpus_available_embedding_space
                ON workspace_corpus_generations;
                DROP FUNCTION IF EXISTS enforce_active_corpus_available_embedding_space();
                SQL);
        }

        if (Schema::hasColumn('workspaces', 'active_workspace_corpus_generation_id')) {
            Schema::table('workspaces', function (Blueprint $table): void {
                $table->dropForeign('workspaces_active_corpus_workspace_foreign');
                $table->dropColumn('active_workspace_corpus_generation_id');
            });
        }

        Schema::dropIfExists('workspace_corpus_generations');
    }
};
