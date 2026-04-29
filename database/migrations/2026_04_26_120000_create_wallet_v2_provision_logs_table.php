<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_v2_provision_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('job_type', 128)->default('ProvisionUserWalletsV2');
            $table->string('trigger', 32)->nullable();
            $table->string('status', 16);
            $table->text('error_message')->nullable();
            $table->json('error_json')->nullable();
            $table->longText('raw_error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_v2_provision_logs');
    }
};
