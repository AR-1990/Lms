<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance and fee automation settings: the time after which students with no mark are made absent
 * (null = off) and how many days before the due date families are reminded (null = off). Each challan
 * remembers when its reminder went out so it is sent once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->time('auto_absent_at')->nullable()->after('fee_auto_generate_day');
            $table->unsignedTinyInteger('fee_reminder_days')->nullable()->after('auto_absent_at');
        });

        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_absent_at', 'fee_reminder_days']);
        });
    }
};
