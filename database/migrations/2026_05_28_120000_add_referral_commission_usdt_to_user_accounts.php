<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('user_accounts', 'referral_commission_usdt')) {
                $table->decimal('referral_commission_usdt', 20, 8)->default(0)->after('referral_earning_naira');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('user_accounts', 'referral_commission_usdt')) {
                $table->dropColumn('referral_commission_usdt');
            }
        });
    }
};
