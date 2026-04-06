<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('wallet_flow_version', 10)->default('v1');
        });

        Schema::table('virtual_accounts', function (Blueprint $table) {
            $table->boolean('is_tatum_ledger')->default(true)->after('account_id');
        });

        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->string('version', 10)->default('v1')->after('virtual_account_id');
            $table->string('tatum_v4_chain')->nullable()->after('address');
            $table->string('tatum_subscription_native_id')->nullable();
            $table->string('tatum_subscription_fungible_id')->nullable();
        });

        Schema::create('user_blockchain_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chain_key', 64);
            $table->text('mnemonic_ciphertext')->nullable();
            $table->text('private_key_ciphertext')->nullable();
            $table->text('xpub')->nullable();
            $table->string('primary_address')->nullable();
            $table->json('tatum_wallet_response')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'chain_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blockchain_wallets');

        Schema::table('deposit_addresses', function (Blueprint $table) {
            $table->dropColumn([
                'version',
                'tatum_v4_chain',
                'tatum_subscription_native_id',
                'tatum_subscription_fungible_id',
            ]);
        });

        Schema::table('virtual_accounts', function (Blueprint $table) {
            $table->dropColumn('is_tatum_ledger');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wallet_flow_version');
        });
    }
};
