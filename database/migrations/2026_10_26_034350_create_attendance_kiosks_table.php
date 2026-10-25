<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance kiosks: a public link (a tablet or phone at the gate) that scans ID-card QR codes. Only the
 * token's hash is stored; switching a kiosk off stops its link at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_kiosks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('scan_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_kiosks');
    }
};
