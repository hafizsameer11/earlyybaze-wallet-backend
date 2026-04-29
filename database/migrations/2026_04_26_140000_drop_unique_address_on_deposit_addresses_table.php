<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deposit_addresses')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $database = Schema::getConnection()->getDatabaseName();
            $indexes = DB::select(
                'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME != ?',
                [$database, 'deposit_addresses', 'address', 'PRIMARY']
            );

            foreach ($indexes as $row) {
                $name = is_array($row)
                    ? ($row['INDEX_NAME'] ?? null)
                    : ($row->INDEX_NAME ?? $row->index_name ?? null);
                if (! $name) {
                    continue;
                }
                $safe = str_replace('`', '``', $name);
                DB::statement("ALTER TABLE `deposit_addresses` DROP INDEX `{$safe}`");
            }

            return;
        }

        try {
            Schema::table('deposit_addresses', function (Blueprint $table) {
                $table->dropUnique(['address']);
            });
        } catch (\Throwable) {
            // Index missing or non-standard name (already dropped / non-Laravel schema).
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('deposit_addresses')) {
            return;
        }

        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->unique('address');
        });
    }
};
