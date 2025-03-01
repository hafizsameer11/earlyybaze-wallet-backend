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
        Schema::create('transaction_sends', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type'); // 'internal' or 'on_chain'
            $table->string('sender_virtual_account_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->foreign('receiver_id')->references('id')->on('users');
            $table->string('receiver_virtual_account_id')->nullable();
            $table->string('sender_address')->nullable();
            $table->string('receiver_address');
            $table->decimal('amount', 20, 8);
            $table->string('currency');
            $table->string('tx_id')->nullable()->unique();
            $table->bigInteger('block_height')->nullable();
            $table->string('block_hash')->nullable();
            $table->decimal('gas_fee', 20, 8)->nullable();
            $table->string('status')->default('pending');
            $table->string('blockchain')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_sends');
    }
};
