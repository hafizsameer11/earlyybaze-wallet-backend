<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Previous failed run may have created the table without the index.
        Schema::dropIfExists('tatum_webhook_hmac_verification_events');

        Schema::create('tatum_webhook_hmac_verification_events', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('path')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->boolean('hmac_enabled')->default(false);
            $table->boolean('enforce')->default(false);
            $table->boolean('verified')->default(false);

            $table->string('provided_payload_hash', 255)->nullable();
            $table->string('computed_payload_hash', 255)->nullable();
            $table->string('failure_reason', 64)->nullable();

            $table->unsignedInteger('raw_body_len')->default(0);
            $table->string('request_id', 64)->nullable();

            $table->timestamps();

            $table->index(['channel', 'verified', 'created_at'], 'twh_hmac_evt_ch_ver_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tatum_webhook_hmac_verification_events');
    }
};

