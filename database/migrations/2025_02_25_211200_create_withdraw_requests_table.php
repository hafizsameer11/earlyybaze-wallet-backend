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
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->id();
            //create foreign key with user
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            //cerete foreign key with bank account
            $table->foreignId('bank_account_id')->constrained()->onDelete('cascade');
            $table->double('amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('reference')->nullable();
            $table->double('fee', 10, 2)->default(0);
            $table->double('total', 10, 2)->default(0);
            $table->string('asset')->default('naira');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraw_requests');
    }
};
