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
        Schema::create('deposit_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_account_id')->constrained()->onDelete('cascade'); // Links to virtual_accounts
            $table->string('blockchain')->nullable(); // Blockchain (e.g., Bitcoin, Ethereum)
            $table->string('currency')->nullable(); // Currency (e.g., BTC, USDT)
            $table->string('address')->unique(); // Deposit address
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_addresses');
    }
};
