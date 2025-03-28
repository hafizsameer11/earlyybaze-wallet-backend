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
        Schema::create('master_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('master_wallet_id')->constrained()->onDelete('cascade');
            $table->string('blockchain')->nullable();
            $table->string('currency')->nullable();
            $table->string('to_address')->nullable();
            $table->decimal('amount', 30, 10)->default(0);
            $table->decimal('fee', 30, 10)->default(0);
            $table->string('tx_hash')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_wallet_transactions');
    }
};
