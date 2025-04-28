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
        Schema::create('admin_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('blockchain')->nullable();
            $table->string('currency')->nullable();
            $table->string('address')->nullable();
            $table->string('forAll')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_transfers');
    }
};
