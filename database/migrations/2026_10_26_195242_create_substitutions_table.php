<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cover for one timetable period on one date when its teacher is on leave or absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('substitutions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->unsignedBigInteger('timetable_slot_id')->index();
            $table->unsignedBigInteger('absent_teacher_id')->index();
            $table->unsignedBigInteger('substitute_teacher_id')->index();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['date', 'timetable_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitutions');
    }
};
