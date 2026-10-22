<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module 8 — Accounts & general ledger, plus payment methods and vendors (PLAN.md §10–11).
     * Money is stored in paisa (bigInteger). No FK constraints: references are indexed columns.
     */
    public function up(): void
    {
        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('normal_balance', 6);
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->unsignedBigInteger('account_type_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('code', 20)->index();
            $table->string('name')->index();
            $table->boolean('is_group')->default(false)->index();
            $table->boolean('is_bank_account')->default(false);
            $table->boolean('is_cash_account')->default(false);
            $table->boolean('is_system')->default(false);
            $table->bigInteger('opening_balance')->default(0);
            $table->string('opening_balance_type', 6)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('bank_id')->index();
            $table->string('branch')->nullable();
            $table->string('account_title');
            $table->string('account_number', 50)->index();
            $table->string('iban', 34)->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->boolean('is_default_for_fees')->default(false);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('status', 20)->default('open')->index();
            $table->unsignedBigInteger('closed_by')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('voucher_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 10)->index();
            $table->string('number_sequence_type', 50);
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('voucher_no', 50)->unique();
            $table->unsignedBigInteger('voucher_type_id')->index();
            $table->unsignedBigInteger('fiscal_year_id')->index();
            $table->date('entry_date')->index();
            $table->text('narration')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->bigInteger('total_debit')->default(0);
            $table->bigInteger('total_credit')->default(0);
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('posted_by')->nullable()->index();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('reversed_by_entry_id')->nullable()->index();
            $table->unsignedBigInteger('reverses_entry_id')->nullable()->index();
            $table->string('reversal_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id')->index();
            $table->unsignedBigInteger('account_id')->index();
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->string('description')->nullable();
            $table->string('party_type')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable()->index();
            $table->smallInteger('line_no')->default(0);
            $table->timestamps();
            $table->index(['party_type', 'party_id']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->index();
            $table->unsignedBigInteger('account_id')->index();
            $table->boolean('requires_reference')->default(false);
            $table->boolean('requires_bank_details')->default(false);
            $table->boolean('is_online')->default(false);
            $table->decimal('charges_percent', 5, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->smallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('ntn', 30)->nullable();
            $table->string('strn', 30)->nullable();
            $table->unsignedBigInteger('payable_account_id')->nullable()->index();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['vendors', 'payment_methods', 'journal_lines', 'journal_entries', 'voucher_types', 'fiscal_years', 'bank_accounts', 'accounts', 'account_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
