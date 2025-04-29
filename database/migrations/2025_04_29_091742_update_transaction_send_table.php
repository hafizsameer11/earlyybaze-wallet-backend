<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaction_sends', function (Blueprint $table) {
            $table->float('amount_usd')->after('amount')->nullable();
            $table->float('network_fee')->after('amount_usd')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_sends', function (Blueprint $table) {
            //
        });
    }
};
