<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestampTz('continuation_blocked_at')->nullable()->after('selected_location_id');
            $table->string('continuation_block_reason', 64)->nullable()->after('continuation_blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['continuation_blocked_at', 'continuation_block_reason']);
        });
    }
};
