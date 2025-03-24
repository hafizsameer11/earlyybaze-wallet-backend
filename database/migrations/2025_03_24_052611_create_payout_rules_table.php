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
        Schema::create('payout_rules', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_event'); // e.g. user_sign_up_with_referral_code
            $table->decimal('trade_amount', 20, 2)->nullable();
            $table->string('time_frame'); // e.g. Daily, Weekly
            $table->string('action_type'); // e.g. credit_users
            $table->string('wallet_type'); // e.g. dollar_wallet
            $table->decimal('payout_amount', 20, 2); // Amount to credit
            $table->text('description')->nullable(); // Optional text to describe the rule
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_rules');
    }
};
