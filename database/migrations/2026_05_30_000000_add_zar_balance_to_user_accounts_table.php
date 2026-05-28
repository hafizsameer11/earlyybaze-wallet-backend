<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('user_accounts', 'zar_balance')) {
                $table->decimal('zar_balance', 20, 8)->default(0)->after('naira_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('user_accounts', 'zar_balance')) {
                $table->dropColumn('zar_balance');
            }
        });
    }
};
