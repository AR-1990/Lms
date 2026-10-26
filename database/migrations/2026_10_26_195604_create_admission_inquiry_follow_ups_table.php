<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every call / visit / message to an enquiring family, with what came of it and when to follow up next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_inquiry_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admission_inquiry_id')->index();
            $table->date('followed_up_on')->index();
            $table->unsignedBigInteger('channel_id')->nullable()->index();
            $table->text('outcome');
            $table->date('next_follow_up_on')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        foreach (['Phone call', 'WhatsApp', 'School visit', 'SMS', 'Email'] as $index => $name) {
            if (DB::table('lookups')->where('type', 'follow_up_channel')->where('name', $name)->doesntExist()) {
                DB::table('lookups')->insert(['type' => 'follow_up_channel', 'name' => $name, 'sort_order' => ($index + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_inquiry_follow_ups');
    }
};
