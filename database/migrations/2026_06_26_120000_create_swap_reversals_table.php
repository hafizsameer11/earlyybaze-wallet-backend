<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('swap_transactions', function (Blueprint $table) {
            $table->string('reversed_fiat', 32)->nullable()->after('balance_before');
            $table->string('reversed_crypto', 32)->nullable()->after('reversed_fiat');
        });

        Schema::create('swap_reversals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('swap_transaction_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('reversal_type', 32);
            $table->string('fiat_currency', 8);
            $table->string('fiat_amount_recovered', 32);
            $table->string('crypto_currency', 32);
            $table->string('crypto_network', 64)->nullable();
            $table->string('crypto_amount_returned', 32);
            $table->string('original_fiat_amount', 32)->nullable();
            $table->string('original_crypto_amount', 32)->nullable();
            $table->string('exchange_rate_used', 32)->nullable();
            $table->string('user_fiat_balance_before', 32)->nullable();
            $table->text('admin_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('swap_transaction_id')->references('id')->on('swap_transactions');
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['swap_transaction_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swap_reversals');

        Schema::table('swap_transactions', function (Blueprint $table) {
            $table->dropColumn(['reversed_fiat', 'reversed_crypto']);
        });
    }
};
