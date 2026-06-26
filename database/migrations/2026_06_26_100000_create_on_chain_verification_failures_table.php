<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('on_chain_verification_failures', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // deposit | flush
            $table->unsignedBigInteger('received_asset_id')->nullable()->index();
            $table->string('tx_id')->nullable()->index();
            $table->string('currency', 32)->nullable();
            $table->string('chain', 64)->nullable();
            $table->string('expected_from')->nullable();
            $table->string('expected_to')->nullable();
            $table->string('expected_amount', 64)->nullable();
            $table->string('failure_code', 64)->index();
            $table->text('failure_message')->nullable();
            $table->json('tatum_response')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->string('reference')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('resolution', 32)->nullable(); // approved | dismissed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('on_chain_verification_failures');
    }
};
