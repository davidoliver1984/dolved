<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_runs', fn (Blueprint $table) => $table->jsonb('needs_profile')->nullable());
    }

    public function down(): void
    {
        Schema::table('generation_runs', fn (Blueprint $table) => $table->dropColumn('needs_profile'));
    }
};
