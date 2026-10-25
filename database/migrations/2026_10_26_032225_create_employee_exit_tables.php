<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff exit: the exit itself (type, reason, notice, last working day), each area's clearance sign-off,
 * and the final settlement lines (salary for the last days, leave encashment, loan recovery, extras)
 * paid out as one payment voucher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_exits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('exit_type', 20)->index();
            $table->unsignedBigInteger('reason_id')->nullable()->index();
            $table->date('notice_date')->nullable();
            $table->date('last_working_day')->index();
            $table->string('status', 20)->default('clearance')->index();
            $table->bigInteger('final_settlement_amount')->nullable();
            $table->date('settlement_paid_on')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->text('exit_interview_notes')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exit_clearances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_exit_id')->index();
            $table->unsignedBigInteger('area_id')->index();
            $table->boolean('cleared')->default(false);
            $table->unsignedBigInteger('cleared_by')->nullable()->index();
            $table->timestamp('cleared_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exit_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_exit_id')->index();
            $table->string('source', 20);
            $table->string('label');
            $table->string('type', 12);
            $table->bigInteger('amount');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();
        $lookups = [
            'exit_clearance_area' => ['Accounts', 'Library', 'IT', 'Administration'],
        ];

        foreach ($lookups as $type => $names) {
            foreach ($names as $index => $name) {
                if (DB::table('lookups')->where('type', $type)->where('name', $name)->doesntExist()) {
                    DB::table('lookups')->insert(['type' => $type, 'name' => $name, 'sort_order' => ($index + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_settlement_lines');
        Schema::dropIfExists('exit_clearances');
        Schema::dropIfExists('employee_exits');
    }
};
