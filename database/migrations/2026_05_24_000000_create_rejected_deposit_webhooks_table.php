<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rejected_deposit_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 8); // v1 | v2
            $table->string('rejection_reason', 64);
            $table->string('subscription_type')->nullable();
            $table->string('tx_id', 128)->nullable();
            $table->unsignedInteger('log_index')->nullable();
            $table->string('contract_address', 128)->nullable();
            $table->string('payload_currency', 32)->nullable();
            $table->string('account_currency', 32)->nullable();
            $table->decimal('amount', 36, 18)->nullable();
            $table->string('chain', 64)->nullable();
            $table->string('from_address', 128)->nullable();
            $table->string('to_address', 128)->nullable();
            $table->string('account_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('token_symbol', 64)->nullable();
            $table->string('token_name', 128)->nullable();
            $table->string('reference')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('tx_id');
            $table->index('contract_address');
            $table->index('rejection_reason');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rejected_deposit_webhooks');
    }
};
