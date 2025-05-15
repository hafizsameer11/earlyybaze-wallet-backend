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
        Schema::table('referal_earnings', function (Blueprint $table) {
            $table->foreignId('swap_transaction_id')->nullable()->constrained('swap_transactions')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referal_earnings', function (Blueprint $table) {
            //
        });
    }
};
