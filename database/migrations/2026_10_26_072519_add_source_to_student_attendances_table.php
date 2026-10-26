<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a student's attendance was recorded: manual (the register), qr (staff scanner) or kiosk (gate),
 * so the live board can tell gate arrivals from the teacher's register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->index()->after('check_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
