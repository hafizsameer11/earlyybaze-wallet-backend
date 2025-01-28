<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('blockchain'); // e.g., bitcoin, ethereum, xrp
            $table->string('xpub')->nullable(); // For EVM/UTXO blockchains
            $table->string('address')->nullable(); // For XRP/XLM or EVM
            $table->string('private_key')->nullable(); // Store securely if required
            $table->string('mnemonic')->nullable(); // Store securely if required
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_wallets');
    }
};
