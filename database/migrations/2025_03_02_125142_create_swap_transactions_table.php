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
        Schema::create('swap_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->string('currency')->nullable();
            $table->string('network')->nullable();
            $table->double('amount')->nullable();
            $table->string('fee')->nullable();
            $table->string('amount_usd')->nullable();
            $table->string('amount_naira')->nullable();
            $table->string('status')->nullable();
            $table->double('fee_naira')->nullable();
            $table->string('exchange_rate')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('swap_transactions');
    }
};
