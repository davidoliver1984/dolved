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
        Schema::table('workspace_memberships', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->after('id');
        });
        DB::table('workspace_memberships')->orderBy('id')->each(function (object $membership): void {
            DB::table('workspace_memberships')->where('id', $membership->id)->update([
                'public_id' => (string) Str::uuid(),
            ]);
        });
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('workspace_memberships', function (Blueprint $table): void {
                $table->uuid('public_id')->nullable(false)->change();
            });
        }
        Schema::table('workspace_memberships', function (Blueprint $table): void {
            $table->unique('public_id');
        });

        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('invited_email');
            $table->string('intended_role');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_digest', 64)->unique();
            $table->string('status');
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'status', 'created_at']);
        });
        DB::statement("CREATE UNIQUE INDEX workspace_invitations_pending_email_unique ON workspace_invitations (workspace_id, invited_email) WHERE status = 'pending'");

        Schema::create('workspace_administration_commands', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->string('command_type');
            $table->string('request_digest', 64);
            $table->json('result')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'idempotency_key'], 'workspace_admin_commands_workspace_key_unique');
        });

        Schema::create('workspace_administration_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type');
            $table->string('target_public_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('context')->nullable();
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index(['workspace_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_administration_audit_events');
        Schema::dropIfExists('workspace_administration_commands');
        DB::statement('DROP INDEX IF EXISTS workspace_invitations_pending_email_unique');
        Schema::dropIfExists('workspace_invitations');
        Schema::table('workspace_memberships', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
