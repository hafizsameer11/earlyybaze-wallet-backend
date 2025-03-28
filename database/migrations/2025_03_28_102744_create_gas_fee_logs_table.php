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
        Schema::create('gas_fee_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('blockchain')->nullable(); // e.g., ETH, TRX, SOL, etc.
            $table->string('currency')->nullable(); // optional, in case native coin is used for token transfer
            $table->decimal('estimated_fee', 30, 10);
            $table->string('fee_currency')->nullable(); // e.g., ETH, TRX, SOL, etc.
            $table->string('tx_type')->nullable(); // e.g., to_master, withdrawal, internal_transfer
            $table->string('tx_hash')->nullable();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gas_fee_logs');
    }
};
