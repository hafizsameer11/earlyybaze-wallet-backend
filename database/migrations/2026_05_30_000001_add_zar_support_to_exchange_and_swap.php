<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('exchange_rates', 'rate_zar')) {
                $table->double('rate_zar')->nullable()->after('rate_naira');
            }
        });

        Schema::table('swap_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('swap_transactions', 'fiat_currency')) {
                $table->string('fiat_currency', 8)->default('NGN')->after('amount_naira');
            }
            if (! Schema::hasColumn('swap_transactions', 'amount_zar')) {
                $table->string('amount_zar')->nullable()->after('fiat_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('swap_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('swap_transactions', 'amount_zar')) {
                $table->dropColumn('amount_zar');
            }
            if (Schema::hasColumn('swap_transactions', 'fiat_currency')) {
                $table->dropColumn('fiat_currency');
            }
        });

        Schema::table('exchange_rates', function (Blueprint $table) {
            if (Schema::hasColumn('exchange_rates', 'rate_zar')) {
                $table->dropColumn('rate_zar');
            }
        });
    }
};
