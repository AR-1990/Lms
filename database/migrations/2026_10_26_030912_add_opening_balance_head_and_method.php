<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opening fee balances brought in by the data import: the "Previous Balance" fee head (Dr Student Receivable /
 * Cr Opening Balance Equity 3300) and the inactive system payment method that records an opening advance.
 */
return new class extends Migration
{
    public function up(): void
    {
        $equity = DB::table('accounts')->where('code', '3300')->value('id');
        $now = now();

        if ($equity === null) {
            return;
        }

        if (DB::table('fee_heads')->where('code', 'OPB')->doesntExist()) {
            DB::table('fee_heads')->insert(['name' => 'Previous Balance', 'code' => 'OPB', 'income_account_id' => $equity, 'is_refundable' => false, 'is_concession_allowed' => false, 'sort_order' => 110, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        if (DB::table('payment_methods')->where('code', 'OPENING')->doesntExist()) {
            DB::table('payment_methods')->insert(['name' => 'Opening advance (import)', 'code' => 'OPENING', 'account_id' => $equity, 'requires_reference' => false, 'requires_bank_details' => false, 'is_online' => false, 'is_active' => false, 'sort_order' => 998, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Seeded master data stays; nothing else depends on its removal.
    }
};
