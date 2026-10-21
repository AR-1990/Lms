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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('platform', 10);
            $table->string('device_name')->nullable();
            $table->string('device_id')->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('push_token')->nullable();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['user_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
