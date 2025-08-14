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
        Schema::create('on_chain_transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->text('tx')->nullable();
            $table->unsignedBigInteger('received_asset_id')->nullable();
            $table->foreign('received_asset_id')->references('id')->on('received_assets');
            $table->text('gas_fee')->nullable();
            $table->string('address_to_send')->nullable();
            $table->string('status')->nullable()->default('completed');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('on_chain_transfer_logs');
    }
};
