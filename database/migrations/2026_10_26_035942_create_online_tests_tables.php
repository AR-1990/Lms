<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online MCQ tests a teacher builds for one section and subject: questions with options (one correct),
 * each student's attempt and chosen answers. A test linked to an exam paper writes its score into marks.
 */
return new class extends Migration
{
    public function up(): void
    {
        $audit = function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        };

        Schema::create('online_tests', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedBigInteger('academic_session_id')->nullable()->index();
            $table->unsignedBigInteger('section_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->unsignedBigInteger('exam_subject_id')->nullable()->index();
            $table->dateTime('opens_at')->nullable()->index();
            $table->dateTime('closes_at')->nullable()->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedInteger('total_marks')->default(0);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('show_answers')->default(false);
            $table->string('status', 20)->default('draft')->index();
            $audit($table);
        });

        Schema::create('online_test_questions', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('online_test_id')->index();
            $table->text('question');
            $table->unsignedSmallInteger('marks')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $audit($table);
        });

        Schema::create('online_test_options', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('online_test_question_id')->index();
            $table->string('option_text', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $audit($table);
        });

        Schema::create('online_test_attempts', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('online_test_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('enrollment_id')->nullable()->index();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable()->index();
            $table->unsignedInteger('score')->nullable();
            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->string('status', 20)->default('in_progress')->index();
            $audit($table);
            $table->index(['online_test_id', 'student_id']);
        });

        Schema::create('online_test_answers', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('online_test_attempt_id')->index();
            $table->unsignedBigInteger('online_test_question_id')->index();
            $table->unsignedBigInteger('online_test_option_id')->nullable()->index();
            $table->boolean('is_correct')->default(false);
            $audit($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_test_answers');
        Schema::dropIfExists('online_test_attempts');
        Schema::dropIfExists('online_test_options');
        Schema::dropIfExists('online_test_questions');
        Schema::dropIfExists('online_tests');
    }
};
