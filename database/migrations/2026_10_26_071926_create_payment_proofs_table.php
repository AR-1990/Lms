<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online payment proofs: a parent pays by JazzCash / EasyPaisa / bank transfer and sends the screenshot
 * with the transaction ID; fee staff check it and approve (a normal receipt is made) or reject it.
 * Payment methods get the instructions families see ("Send to JazzCash 0300…").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('fee_invoice_id')->nullable()->index();
            $table->unsignedBigInteger('payment_method_id')->index();
            $table->bigInteger('amount');
            $table->date('paid_on')->index();
            $table->string('reference_no', 100)->index();
            $table->string('payer_name', 150)->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->unsignedBigInteger('fee_payment_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->text('payment_instructions')->nullable()->after('is_online');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('payment_instructions');
        });

        Schema::dropIfExists('payment_proofs');
    }
};
