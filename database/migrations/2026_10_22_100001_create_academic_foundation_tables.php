<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module 1 — Academic foundation (PLAN.md §4). No FK constraints: references are indexed columns.
     */
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('name', 30);
            $table->date('starts_on')->index();
            $table->date('ends_on');
            $table->boolean('is_current')->default(false)->index();
            $table->boolean('admissions_open')->default(false);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('code', 20)->nullable()->index();
            $table->smallInteger('level_order')->index();
            $table->unsignedBigInteger('wing_id')->nullable()->index();
            $table->unsignedBigInteger('next_class_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id')->index();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->string('name', 20);
            $table->string('room', 50)->nullable();
            $table->smallInteger('capacity')->nullable();
            $table->unsignedBigInteger('class_teacher_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['academic_session_id', 'class_id', 'name']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();
            $table->string('code', 20)->nullable()->index();
            $table->unsignedBigInteger('subject_type_id')->nullable()->index();
            $table->boolean('has_practical')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('class_subject', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->unsignedBigInteger('class_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->boolean('is_optional')->default(false);
            $table->unsignedTinyInteger('periods_per_week')->nullable();
            $table->smallInteger('theory_max_marks')->nullable();
            $table->smallInteger('practical_max_marks')->nullable();
            $table->smallInteger('pass_marks')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->unique(['academic_session_id', 'class_id', 'subject_id']);
        });

        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('name', 50);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_break')->default(false);
            $table->smallInteger('sort_order')->default(0)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periods');
        Schema::dropIfExists('class_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('academic_sessions');
    }
};
