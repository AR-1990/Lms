<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fee head frequency becomes a dynamic option (lookups type `fee_frequency`) instead of a fixed list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_heads', function (Blueprint $table): void {
            $table->unsignedBigInteger('frequency_id')->nullable()->index()->after('income_account_id');
        });

        foreach (DB::table('fee_heads')->whereNotNull('frequency')->distinct()->pluck('frequency') as $frequency) {
            $name = Str::headline($frequency);
            $lookupId = DB::table('lookups')->where('type', 'fee_frequency')->where('name', $name)->value('id')
                ?? DB::table('lookups')->insertGetId(['type' => 'fee_frequency', 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

            DB::table('fee_heads')->where('frequency', $frequency)->update(['frequency_id' => $lookupId]);
        }

        Schema::table('fee_heads', fn (Blueprint $table) => $table->dropColumn('frequency'));
    }

    public function down(): void
    {
        Schema::table('fee_heads', fn (Blueprint $table) => $table->string('frequency', 20)->nullable()->after('income_account_id'));

        foreach (DB::table('fee_heads')->whereNotNull('frequency_id')->get(['id', 'frequency_id']) as $head) {
            DB::table('fee_heads')->where('id', $head->id)->update(['frequency' => Str::snake((string) DB::table('lookups')->where('id', $head->frequency_id)->value('name'))]);
        }

        Schema::table('fee_heads', fn (Blueprint $table) => $table->dropColumn('frequency_id'));
    }
};
