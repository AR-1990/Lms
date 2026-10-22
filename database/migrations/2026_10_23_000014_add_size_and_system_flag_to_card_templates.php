<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Card sizes come from a fixed list per card type (ID cards stay vertical CR80), and the
 * built-in professional templates are marked as system templates that cannot be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_templates', function (Blueprint $table): void {
            $table->string('size_key', 20)->default('cr80_portrait')->after('orientation');
            $table->boolean('is_system')->default(false)->after('is_default');
        });

        DB::table('card_templates')->where('width_mm', '>', DB::raw('height_mm'))->update(['size_key' => 'cr80_landscape']);
    }

    public function down(): void
    {
        Schema::table('card_templates', fn (Blueprint $table) => $table->dropColumn(['size_key', 'is_system']));
    }
};
