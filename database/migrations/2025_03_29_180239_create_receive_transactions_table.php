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
        Schema::create('receive_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('virtual_account_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('virtual_account_id')->references('id')->on('virtual_accounts');
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->string('transaction_type'); // 'internal' or 'on_chain'
            $table->string('sender_address')->nullable();
            $table->string('reference')->nullable()->unique();
            $table->text('tx_id')->nullable()->unique();
            $table->double('amount')->nullable();
            $table->string('currency')->nullable();
            $table->string('blockchain')->nullable();
            $table->double('amount_usd')->nullable();
            $table->string('status')->default('pending');
            // $table->string('block_height')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receive_transactions');
    }
};
