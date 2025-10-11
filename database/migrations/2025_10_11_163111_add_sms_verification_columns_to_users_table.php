<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sms_type')->nullable()->comment('whatsapp or sms');
            $table->string('sms_code')->nullable()->comment('phone verification code');
            $table->boolean('is_number_verified')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sms_type', 'sms_code', 'is_number_verified']);
        });
    }
};
