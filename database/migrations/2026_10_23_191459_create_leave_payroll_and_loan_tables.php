<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 14 (PLAN.md §14.2–14.4): leave types, balances and requests; salary components, salary
 * structures, payroll runs and payslips (posted to the ledger); staff loans and advances.
 * Money is in paisa (bigInteger). No foreign-key constraints; every reference is indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $audit = function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        };

        /* ---------- Leave ---------- */

        Schema::create('leave_types', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->decimal('days_per_year', 5, 1)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('carry_forward')->default(false);
            $table->decimal('max_carry_forward', 5, 1)->nullable();
            $table->boolean('requires_document')->default(false);
            $table->unsignedBigInteger('applicable_gender_id')->nullable()->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('leave_type_id')->index();
            $table->unsignedSmallInteger('year')->index();
            $table->decimal('allocated', 5, 1)->default(0);
            $table->decimal('carried_forward', 5, 1)->default(0);
            $table->decimal('used', 5, 1)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leave_requests', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('leave_type_id')->index();
            $table->date('from_date')->index();
            $table->date('to_date')->index();
            $table->decimal('days', 5, 1);
            $table->boolean('is_half_day')->default(false);
            $table->text('reason');
            $table->unsignedBigInteger('substitute_employee_id')->nullable()->index();
            $table->string('status', 12)->default('pending')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->dateTime('approved_at')->nullable();
            $table->string('review_remarks', 500)->nullable();
            $audit($table);
        });

        /* ---------- Payroll ---------- */

        Schema::create('salary_components', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->index();
            $table->string('type', 10)->index();
            $table->string('calculation', 20)->default('fixed');
            $table->bigInteger('default_value')->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('employee_salary_structures', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->bigInteger('basic_salary');
            $table->bigInteger('gross_salary')->default(0);
            $table->string('remarks', 500)->nullable();
            $audit($table);
        });

        Schema::create('employee_salary_structure_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_salary_structure_id')->index('salary_structure_items_structure_id_index');
            $table->unsignedBigInteger('salary_component_id')->index();
            $table->bigInteger('amount');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('salary_increments', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->date('effective_from');
            $table->bigInteger('old_basic')->default(0);
            $table->bigInteger('new_basic');
            $table->decimal('increment_percent', 6, 2)->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $audit($table);
        });

        Schema::create('tax_slabs', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id')->index();
            $table->bigInteger('min_income');
            $table->bigInteger('max_income')->nullable();
            $table->bigInteger('fixed_tax')->default(0);
            $table->decimal('rate_percent', 5, 2)->default(0);
            $audit($table);
        });

        Schema::create('payroll_runs', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->date('month')->index();
            $table->unsignedBigInteger('fiscal_year_id')->nullable()->index();
            $table->string('status', 12)->default('draft')->index();
            $table->bigInteger('total_gross')->default(0);
            $table->bigInteger('total_deductions')->default(0);
            $table->bigInteger('total_net')->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->unsignedBigInteger('payment_journal_entry_id')->nullable()->index();
            $table->dateTime('paid_at')->nullable();
            $audit($table);
        });

        Schema::create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->decimal('working_days', 4, 1)->default(0);
            $table->decimal('present_days', 4, 1)->default(0);
            $table->decimal('leave_days', 4, 1)->default(0);
            $table->decimal('unpaid_leave_days', 4, 1)->default(0);
            $table->decimal('absent_days', 4, 1)->default(0);
            $table->bigInteger('gross')->default(0);
            $table->bigInteger('deductions')->default(0);
            $table->bigInteger('net')->default(0);
            $table->unsignedBigInteger('payment_method_id')->nullable()->index();
            $table->date('paid_on')->nullable();
            $table->string('bank_reference', 100)->nullable();
            $table->string('status', 10)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payslip_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payslip_id')->index();
            $table->unsignedBigInteger('salary_component_id')->index();
            $table->string('type', 10);
            $table->bigInteger('amount');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        /* ---------- Loans & advances ---------- */

        Schema::create('employee_loans', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('loan_type_id')->nullable()->index();
            $table->bigInteger('amount');
            $table->date('issued_on')->nullable();
            $table->unsignedSmallInteger('instalments');
            $table->bigInteger('instalment_amount');
            $table->date('start_month');
            $table->bigInteger('outstanding')->default(0);
            $table->string('status', 10)->default('pending')->index();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->dateTime('approved_at')->nullable();
            $table->string('review_remarks', 500)->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $audit($table);
        });

        Schema::create('employee_loan_instalments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_loan_id')->index();
            $table->date('due_month')->index();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('payslip_id')->nullable()->index();
            $table->string('status', 10)->default('due')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $permissions = [
            ['name' => 'Manage Leave', 'slug' => 'manage-leave', 'group' => 'hr', 'description' => 'Leave types, approving staff leave and adjusting balances.'],
            ['name' => 'Manage Payroll', 'slug' => 'manage-payroll', 'group' => 'hr', 'description' => 'Salary components and structures, payroll runs, payslips, loans and posting salaries to the ledger.'],
        ];

        foreach ($permissions as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        foreach (['admin' => ['manage-leave', 'manage-payroll'], 'accounts' => ['manage-payroll']] as $roleSlug => $slugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

            foreach ($roleId === null ? [] : $slugs as $slug) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id')],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['manage-leave', 'manage-payroll'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        foreach ([
            'employee_loan_instalments', 'employee_loans', 'payslip_items', 'payslips', 'payroll_runs', 'tax_slabs', 'salary_increments',
            'employee_salary_structure_items', 'employee_salary_structures', 'salary_components', 'leave_requests', 'leave_balances', 'leave_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
