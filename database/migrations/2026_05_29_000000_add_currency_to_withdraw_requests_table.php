<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('withdraw_requests', 'currency')) {
                $table->string('currency', 8)->default('NGN')->after('asset');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            if (Schema::hasColumn('withdraw_requests', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
