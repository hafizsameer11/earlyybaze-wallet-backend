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
        Schema::create('buy_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');
            $table->string('status')->nullable();
            $table->string('currency')->nullable();
            $table->string('network')->nullable();
            $table->string('amount_coin')->nullable();
            $table->string('amount_usd')->nullable();
            $table->string('amount_naira')->nullable();
            $table->string('name_on_account')->nullable();
            $table->string('amount_paid')->nullable();
            $table->string('receipt')->nullable();
            $table->boolean('receipt_attached')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buy_transactions');
    }
};
