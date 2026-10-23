<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17a (PLAN.md §11): expense categories and expenses (draft → approved → paid, or cancelled; the bill
 * scan is an attachment), vendor payments (settling what goods receipts left owed), and bank reconciliation
 * (statement balance vs the bank's ledger account, line by line). Money is in paisa. No foreign-key constraints.
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

        Schema::create('expense_categories', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedBigInteger('expense_account_id')->index();
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('expenses', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('expense_no', 50)->index();
            $table->unsignedBigInteger('expense_category_id')->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->date('expense_date')->index();
            $table->bigInteger('amount');
            $table->bigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('payment_method_id')->nullable()->index();
            $table->string('reference_no', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->date('paid_on')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->text('cancel_reason')->nullable();
            $audit($table);
        });

        Schema::create('vendor_payments', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('payment_no', 50)->index();
            $table->unsignedBigInteger('vendor_id')->index();
            $table->date('paid_on')->index();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('payment_method_id')->index();
            $table->string('reference_no', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('posted')->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->text('reversal_reason')->nullable();
            $audit($table);
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('bank_account_id')->index();
            $table->date('statement_date')->index();
            $table->bigInteger('statement_balance');
            $table->bigInteger('book_balance')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('reconciled_by')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $audit($table);
        });

        Schema::create('bank_reconciliation_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_reconciliation_id')->index();
            $table->unsignedBigInteger('journal_line_id')->index();
            $table->date('cleared_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        if (! DB::table('number_sequences')->where('type', 'vendor_payment_no')->exists()) {
            DB::table('number_sequences')->insert(['type' => 'vendor_payment_no', 'label' => 'Vendor payment', 'prefix' => 'VPY-', 'include_year' => true, 'padding' => 5, 'next_number' => 1, 'reset_yearly' => true, 'current_year' => (int) $now->format('Y'), 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([
            ['name' => 'Manage Expenses', 'slug' => 'manage-expenses', 'group' => 'accounts', 'description' => 'Record and pay expenses and vendor bills; approving them needs Approve Requests.'],
            ['name' => 'Reconcile Bank', 'slug' => 'reconcile-bank', 'group' => 'accounts', 'description' => 'Match bank statements against the bank accounts in the ledger.'],
        ] as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach (['admin' => ['manage-expenses', 'reconcile-bank'], 'accounts' => ['manage-expenses', 'reconcile-bank']] as $roleSlug => $slugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

            foreach ($roleId === null ? [] : $slugs as $slug) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id')],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', ['manage-expenses', 'reconcile-bank'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        foreach (['bank_reconciliation_lines', 'bank_reconciliations', 'vendor_payments', 'expenses', 'expense_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
