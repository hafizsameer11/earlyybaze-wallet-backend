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
        Schema::create('aml_rules', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type'); // e.g. Swap Transaction
            $table->string('condition_operator'); // e.g. is greater than, equals
            $table->decimal('amount', 20, 2);
            $table->string('time_frame'); // e.g. Daily, Weekly
            $table->integer('trigger_count')->nullable(); // Number of times this must happen
        $table->string('action'); // e.g. freeze_account
            $table->text('action_message')->nullable(); // e.g. "Your account is flagged"
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aml_rules');
    }
};
