<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_audit_reports', function (Blueprint $table) {
            $table->id();
            $table->boolean('success')->default(false);
            $table->string('message')->nullable();
            $table->json('summary')->nullable();
            $table->longText('analysis')->nullable();
            $table->string('triggered_by', 32)->default('cron');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_reports');
    }
};
