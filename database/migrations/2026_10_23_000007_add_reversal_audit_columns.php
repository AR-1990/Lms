<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who cancelled or reversed a posted financial record, and when (the narration is already stored).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('reversed_by_user_id')->nullable()->index()->after('reversal_reason');
            $table->timestamp('reversed_at')->nullable()->after('reversed_by_user_id');
        });

        Schema::table('fee_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('cancelled_by')->nullable()->index()->after('cancelled_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });

        Schema::table('fee_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('reversed_by')->nullable()->index()->after('reversal_reason');
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');
        });
    }

    public function down(): void
    {
        Schema::table('fee_payments', fn (Blueprint $table) => $table->dropColumn(['reversed_by', 'reversed_at']));
        Schema::table('fee_invoices', fn (Blueprint $table) => $table->dropColumn(['cancelled_by', 'cancelled_at']));
        Schema::table('journal_entries', fn (Blueprint $table) => $table->dropColumn(['reversed_by_user_id', 'reversed_at']));
    }
};
