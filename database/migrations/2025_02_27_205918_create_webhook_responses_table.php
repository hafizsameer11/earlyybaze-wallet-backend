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
        Schema::create('webhook_responses', function (Blueprint $table) {
            $table->id();
            $table->string('account_id')->nullable();
            $table->string('subscription_type')->nullable();
            $table->decimal('amount', 20, 8)->nullable();
            $table->string('reference')->nullable();
            $table->string('currency')->nullable();
            $table->string('tx_id')->nullable();
            $table->bigInteger('block_height')->nullable();
            $table->string('block_hash')->nullable();
            $table->string('from_address')->nullable();
            $table->string('to_address')->nullable();
            $table->timestamp('transaction_date')->nullable();
            $table->integer('index')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_responses');
    }
};
