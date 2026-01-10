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
        // Drop foreign key constraint first
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
        });
        
        // Modify bank_account_id to be nullable and add new fields
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_account_id')->nullable()->change();
            
            // Add new bank account fields
            $table->string('bank_account_name')->nullable()->after('bank_account_id');
            $table->string('bank_account_code')->nullable()->after('bank_account_name');
            $table->string('account_name')->nullable()->after('bank_account_code');
            $table->string('account_number')->nullable()->after('account_name');
        });
        
        // Note: Foreign key constraint is not re-added for nullable bank_account_id
        // Application will handle referential integrity when bank_account_id is provided
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            // Drop new fields
            $table->dropColumn([
                'bank_account_name',
                'bank_account_code',
                'account_name',
                'account_number'
            ]);
        });
        
        Schema::table('withdraw_requests', function (Blueprint $table) {
            // Restore bank_account_id to be required (non-nullable)
            // First ensure all records have a bank_account_id (or handle nulls)
            $table->unsignedBigInteger('bank_account_id')->nullable(false)->change();
        });
        
        Schema::table('withdraw_requests', function (Blueprint $table) {
            // Re-add foreign key constraint
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
        });
    }
};
