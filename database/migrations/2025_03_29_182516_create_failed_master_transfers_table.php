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
        Schema::create('failed_master_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('virtual_account_id')->nullable();
            $table->unsignedBigInteger('webhook_response_id')->nullable();
            $table->foreign('virtual_account_id')->references('id')->on('virtual_accounts');
            $table->foreign('webhook_response_id')->references('id')->on('webhook_responses');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_master_transfers');
    }
};
