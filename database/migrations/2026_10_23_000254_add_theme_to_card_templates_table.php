<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card look (classic bands, curved wave, elegant diagonal) drawn behind the placed fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_templates', function (Blueprint $table): void {
            $table->string('theme', 20)->default('classic')->after('size_key');
        });
    }

    public function down(): void
    {
        Schema::table('card_templates', fn (Blueprint $table) => $table->dropColumn('theme'));
    }
};
