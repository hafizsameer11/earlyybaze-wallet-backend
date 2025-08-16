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
        Schema::table('received_assets', function (Blueprint $table) {
            // $table->string('status')->default('pending');
            $table->text('transfered_tx')->nullable();
            $table->double('transfered_amount')->nullable();
            $table->double('gas_fee')->nullable();
            $table->text('address_to_send')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('received_assets', function (Blueprint $table) {
            //
        });
    }
};
