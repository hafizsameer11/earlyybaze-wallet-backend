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
            $table->string('original_amount')->nullable();
            $table->string('amount_after_fee')->nullable();
            $table->string('network_fee')->nullable();
            $table->string('platform_fee')->nullable();
            $table->json('fee_summary')->nullable();
            $table->string('fee_actual_transaction')->nullable();
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
