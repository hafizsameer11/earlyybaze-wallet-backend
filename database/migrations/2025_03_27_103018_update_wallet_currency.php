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
        Schema::table('wallet_currencies', function (Blueprint $table) {
            $table->string('token_type')->nullable(); // erc20, trc20, etc.
            $table->string('contract_address')->nullable();
            $table->integer('decimals')->default(18);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_currencies', function (Blueprint $table) {
            //
        });
    }
};
