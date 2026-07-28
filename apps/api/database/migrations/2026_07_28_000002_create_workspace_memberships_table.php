<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workspace_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();
            $table->enum('role', ['owner', 'admin', 'member']);
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'user_id'],
                'workspace_memberships_workspace_user_unique'
            );
            $table->index('user_id');
            $table->index(['workspace_id', 'role']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX workspace_memberships_one_owner_per_workspace
            ON workspace_memberships (workspace_id)
            WHERE role = 'owner'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_memberships');
    }
};
