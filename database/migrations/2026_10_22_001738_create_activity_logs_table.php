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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('module', 40)->index();
            $table->string('action', 30)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->string('description', 500);
            $table->uuid('batch_uuid')->nullable()->index();
            $table->string('platform', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('route')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('activity_log_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activity_log_id')->index();
            $table->string('field', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log_changes');
        Schema::dropIfExists('activity_logs');
    }
};
