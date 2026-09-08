<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_answers', function (Blueprint $table): void {
            $table->jsonb('scope_facts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('generated_answers', fn (Blueprint $table) => $table->dropColumn('scope_facts'));
    }
};
