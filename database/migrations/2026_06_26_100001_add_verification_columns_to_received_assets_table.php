<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_assets', function (Blueprint $table) {
            $table->string('verification_status', 32)->nullable()->after('status');
            $table->string('flush_status', 32)->nullable()->after('verification_status');
            $table->text('verification_error')->nullable()->after('flush_status');
            $table->timestamp('verified_at')->nullable()->after('verification_error');
        });
    }

    public function down(): void
    {
        Schema::table('received_assets', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'flush_status', 'verification_error', 'verified_at']);
        });
    }
};
