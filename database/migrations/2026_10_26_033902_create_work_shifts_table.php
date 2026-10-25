<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff shifts: the start / end time and the minutes of grace after which a check-in counts as late.
 * Each employee may follow a shift; without one, the default shift applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('work_shift_id')->nullable()->index()->after('designation_id');
        });

        if (DB::table('work_shifts')->doesntExist()) {
            DB::table('work_shifts')->insert(['name' => 'School day', 'starts_at' => '08:00:00', 'ends_at' => '14:30:00', 'grace_minutes' => 10, 'is_default' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('work_shift_id');
        });

        Schema::dropIfExists('work_shifts');
    }
};
