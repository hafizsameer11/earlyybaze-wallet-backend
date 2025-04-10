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
        Schema::create('transaction_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('tx')->nullable(); // Store transaction hash

            $table->string('transaction_type')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('amount', 36, 18)->nullable();

            $table->decimal('platform_fee_usd', 20, 8)->nullable();
            $table->decimal('blockchain_fee_usd', 20, 8)->nullable();
            $table->decimal('total_fee_usd', 20, 8)->nullable();
            $table->decimal('fee_currency', 36, 18)->nullable();
            $table->decimal('fee_naira', 36, 8)->nullable();

            $table->string('gas_limit')->nullable();
            $table->string('gas_price')->nullable();
            $table->decimal('native_fee', 36, 18)->nullable();
            $table->decimal('native_fee_doubled', 36, 18)->nullable();
            $table->string('native_currency')->nullable();

            $table->enum('status', ['pending', 'used', 'expired'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_fees');
    }
};
