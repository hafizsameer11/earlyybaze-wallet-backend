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
        Schema::create('referal_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');//user who get the amount
            $table->unsignedBigInteger('referal_id');//user who made the transaction
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('referal_id')->references('id')->on('users');
            $table->double('amount')->nullable();
            $table->string('currency')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->default('done');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referal_earnings');
    }
};
