<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        // SQLite table reconstruction while adding the committed-document
        // relationship cannot preserve a partial-index predicate.
        DB::statement('DROP INDEX IF EXISTS promotion_attempts_one_open_per_item_unique');
        DB::statement(
            "CREATE UNIQUE INDEX promotion_attempts_one_open_per_item_unique
             ON promotion_attempts (import_item_id)
             WHERE status IN ('reserved', 'copying', 'source_verified')"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS promotion_attempts_one_open_per_item_unique');
    }
};
