<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_answers', function (Blueprint $table): void {
            $table->jsonb('temporal_context')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('generated_answers', function (Blueprint $table): void {
            $table->dropColumn('temporal_context');
        });
    }
};
