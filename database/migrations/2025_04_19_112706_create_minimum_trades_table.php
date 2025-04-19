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
        Schema::create('minimum_trades', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('amount')->nullable();
            $table->string('amount_naira')->nullable();
            $table->string('percentage')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minimum_trades');
    }
};
