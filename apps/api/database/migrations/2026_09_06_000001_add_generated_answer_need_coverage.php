<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_answers', function (Blueprint $table): void {
            $table->jsonb('aspect_links')->nullable();
            $table->jsonb('aspect_results')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('generated_answers', function (Blueprint $table): void {
            $table->dropColumn(['aspect_links', 'aspect_results']);
        });
    }
};
