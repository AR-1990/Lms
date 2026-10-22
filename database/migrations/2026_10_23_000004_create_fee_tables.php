<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modules 6–7 — Fee management and fee receipts (PLAN.md §9–10). Money in paisa. No FK constraints.
     */
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->index();
            $table->unsignedBigInteger('income_account_id')->index();
            $table->string('frequency', 20);
            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_concession_allowed')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('concession_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('calculation', 10);
            $table->bigInteger('default_value')->default(0);
            $table->unsignedBigInteger('expense_account_id')->nullable()->index();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->unsignedBigInteger('class_id')->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->unique(['academic_session_id', 'class_id']);
        });

        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_structure_id')->index();
            $table->unsignedBigInteger('fee_head_id')->index();
            $table->string('charge_on', 20);
            $table->bigInteger('amount');
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->smallInteger('due_in_days')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('applies_to_new_students_only')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fee_structure_item_months', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_structure_item_id')->index();
            $table->unsignedTinyInteger('month');
        });

        Schema::create('student_concessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('concession_type_id')->index();
            $table->unsignedBigInteger('fee_head_id')->nullable()->index();
            $table->string('calculation', 10);
            $table->bigInteger('value');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('invoice_no', 30)->unique();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->unsignedBigInteger('enrollment_id')->nullable()->index();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->string('invoice_type', 20)->index();
            $table->unsignedBigInteger('admission_application_id')->nullable()->index();
            $table->unsignedBigInteger('exam_id')->nullable()->index();
            $table->date('billing_month')->nullable()->index();
            $table->date('issue_date')->index();
            $table->date('due_date')->index();
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('concession_total')->default(0);
            $table->bigInteger('fine_total')->default(0);
            $table->bigInteger('waived_total')->default(0);
            $table->bigInteger('previous_balance')->default(0);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('paid_total')->default(0);
            $table->bigInteger('balance')->default(0);
            $table->string('status', 20)->default('posted')->index();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->unsignedBigInteger('fee_invoice_batch_id')->nullable()->index();
            $table->string('cancelled_reason')->nullable();
            $table->string('bank_challan_no', 50)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('fee_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_invoice_id')->index();
            $table->unsignedBigInteger('fee_head_id')->index();
            $table->string('description')->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('concession_amount')->default(0);
            $table->unsignedBigInteger('student_concession_id')->nullable()->index();
            $table->bigInteger('net_amount');
            $table->timestamps();
        });

        Schema::create('fee_invoice_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->date('billing_month')->index();
            $table->unsignedBigInteger('class_id')->nullable()->index();
            $table->integer('generated_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->string('status', 20)->default('processing');
            $table->unsignedBigInteger('generated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('fee_waivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_invoice_id')->index();
            $table->unsignedBigInteger('fee_invoice_item_id')->nullable()->index();
            $table->bigInteger('amount');
            $table->text('reason');
            $table->unsignedBigInteger('approved_by')->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('receipt_no', 30)->unique();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->unsignedBigInteger('admission_application_id')->nullable()->index();
            $table->unsignedBigInteger('paid_by_guardian_id')->nullable()->index();
            $table->unsignedBigInteger('payment_method_id')->index();
            $table->bigInteger('amount');
            $table->bigInteger('advance_amount')->default(0);
            $table->date('received_on')->index();
            $table->string('reference_no', 100)->nullable()->index();
            $table->unsignedBigInteger('cheque_bank_id')->nullable()->index();
            $table->date('cheque_date')->nullable();
            $table->string('cheque_status', 20)->nullable();
            $table->unsignedBigInteger('received_by')->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->string('status', 20)->default('posted')->index();
            $table->string('reversal_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('fee_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_payment_id')->index();
            $table->unsignedBigInteger('fee_invoice_id')->index();
            $table->bigInteger('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['fee_payment_allocations', 'fee_payments', 'fee_waivers', 'fee_invoice_batches', 'fee_invoice_items', 'fee_invoices', 'student_concessions', 'fee_structure_item_months', 'fee_structure_items', 'fee_structures', 'concession_types', 'fee_heads'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
