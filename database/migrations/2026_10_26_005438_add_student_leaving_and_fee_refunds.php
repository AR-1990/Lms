<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student leaving: conduct and remarks printed on the School Leaving Certificate,
 * and fee refunds (security deposit or unused advance) paid out when a student leaves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('slc_conduct_id')->nullable()->index()->after('slc_issued_on');
            $table->text('slc_remarks')->nullable()->after('slc_conduct_id');
        });

        Schema::create('fee_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_no', 30)->unique();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('fee_payment_id')->nullable()->index();
            $table->string('source', 20)->index();
            $table->bigInteger('amount');
            $table->text('reason');
            $table->unsignedBigInteger('payment_method_id')->index();
            $table->string('reference_no', 60)->nullable();
            $table->date('refunded_on')->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
            $table->string('status', 20)->default('posted')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->string('reversal_reason')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable()->index();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_refunds');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['slc_conduct_id', 'slc_remarks']);
        });
    }
};
