<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_dm_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->string('field', 32)->nullable();
            $table->string('sub_type', 64)->nullable();
            $table->string('event_type', 64)->nullable();
            $table->string('message_id', 64)->nullable()->index();
            $table->string('message_status', 32)->nullable()->index();
            $table->string('channel', 32)->nullable();
            $table->string('phone', 32)->nullable()->index();
            $table->string('template_id', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->timestamps();

            $table->index(['message_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_dm_webhook_events');
    }
};
