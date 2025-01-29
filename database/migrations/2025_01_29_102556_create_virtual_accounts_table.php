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
        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Reference to users table
            $table->string('blockchain'); // Blockchain name (e.g., Bitcoin, Ethereum)
            $table->string('currency'); // Currency name (e.g., BTC, ETH)
            $table->string('customer_id')->nullable(); // Tatum Customer ID
            $table->string('account_id')->unique(); // Virtual Account ID from Tatum
            $table->string('account_code')->nullable(); // Custom user account code
            $table->boolean('active')->default(true); // Whether the account is active
            $table->boolean('frozen')->default(false); // Whether the account is frozen
            $table->string('account_balance')->default('0'); // Account balance from Tatum
            $table->string('available_balance')->default('0'); // Available balance from Tatum
            $table->string('xpub')->nullable(); // Extended public key (for UTXO blockchains)
            $table->string('accounting_currency')->nullable(); // Accounting currency (e.g., EUR, USD)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_accounts');
    }
};
