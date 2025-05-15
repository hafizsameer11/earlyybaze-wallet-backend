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
        Schema::create('referal_pay_outs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('referal_earning_id');
            $table->foreign('referal_earning_id')->references('id')->on('referal_earnings');
            $table->string('status')->default('pending');
            $table->string('amount')->nullable();
            $table->string('paid_to_account')->nullable();
            $table->string('paid_to_bank')->nullable();
            $table->string('paid_to_name')->nullable();
            $table->text('exchange_rate')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referal_pay_outs');
    }
};
