<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll detail (PLAN.md §14): late arrivals on each payslip with the school's late policy (so many lates =
 * one day's pay), leave taken per leave type, and one-off monthly adjustments — bonus, incentive, overtime,
 * arrears, fines — picked up by the month's payroll. No foreign-key constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table): void {
            $table->unsignedSmallInteger('late_count')->default(0)->after('absent_days');
            $table->unsignedInteger('late_minutes')->default(0)->after('late_count');
            $table->decimal('late_deduction_days', 4, 1)->default(0)->after('late_minutes');
        });

        Schema::create('payslip_leaves', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payslip_id')->index();
            $table->unsignedBigInteger('leave_type_id')->index();
            $table->decimal('days', 4, 1)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->date('month')->index();
            $table->unsignedBigInteger('salary_component_id')->index();
            $table->bigInteger('amount');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('payslip_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('system_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('late_grace_count')->default(0);
            $table->unsignedTinyInteger('lates_per_deduction_day')->default(3);
        });

        $now = now();
        $expense = DB::table('accounts')->where('code', '5100')->value('id');

        // [name, code, type, taxable, system]
        foreach ([
            ['Late Arrival Deduction', 'LATE', 'deduction', false, true],
            ['Bonus', 'BONUS', 'earning', true, false],
            ['Performance Incentive', 'INCENTIVE', 'earning', true, false],
            ['Overtime', 'OVERTIME', 'earning', true, false],
            ['Arrears', 'ARREARS', 'earning', true, false],
            ['Fine', 'FINE', 'deduction', false, false],
        ] as $position => [$name, $code, $type, $taxable, $system]) {
            if (DB::table('salary_components')->where('code', $code)->doesntExist()) {
                DB::table('salary_components')->insert([
                    'name' => $name, 'code' => $code, 'type' => $type, 'calculation' => 'fixed', 'default_value' => 0, 'is_taxable' => $taxable,
                    'account_id' => $expense, 'is_system' => $system, 'sort_order' => 20 + $position, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('salary_components')->whereIn('code', ['LATE', 'BONUS', 'INCENTIVE', 'OVERTIME', 'ARREARS', 'FINE'])->delete();
        Schema::table('system_settings', fn (Blueprint $table) => $table->dropColumn(['late_grace_count', 'lates_per_deduction_day']));
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payslip_leaves');
        Schema::table('payslips', fn (Blueprint $table) => $table->dropColumn(['late_count', 'late_minutes', 'late_deduction_days']));
    }
};
