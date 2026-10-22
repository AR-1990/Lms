<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 (PLAN.md §8): grading scales, terms, exams with date sheets and exam fees, halls,
 * seating, invigilation, admit cards, marks (with change log) and published report cards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('grading_scale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grading_scale_id')->index();
            $table->string('grade', 5);
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->string('name');
            $table->decimal('weightage_percent', 5, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_final')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->unsignedBigInteger('exam_term_id')->index();
            $table->unsignedBigInteger('class_id')->index();
            $table->string('name');
            $table->unsignedBigInteger('exam_type_id')->nullable()->index();
            $table->unsignedBigInteger('grading_scale_id')->nullable()->index();
            $table->unsignedBigInteger('fee_head_id')->nullable()->index();
            $table->bigInteger('exam_fee_amount')->nullable();
            $table->date('fee_due_date')->nullable();
            $table->boolean('require_fee_clearance')->default(true);
            $table->decimal('min_attendance_percent', 5, 2)->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 30)->index();
            $table->date('marks_entry_deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->date('exam_date')->nullable()->index();
            $table->time('starts_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('theory_max_marks')->default(100);
            $table->unsignedSmallInteger('practical_max_marks')->default(0);
            $table->unsignedSmallInteger('pass_marks')->nullable();
            $table->unsignedBigInteger('paper_setter_id')->nullable()->index();
            $table->text('syllabus')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_halls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('name');
            $table->unsignedSmallInteger('rows')->nullable();
            $table->unsignedSmallInteger('columns')->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_seatings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_subject_id')->index();
            $table->unsignedBigInteger('exam_hall_id')->index();
            $table->unsignedBigInteger('enrollment_id')->index();
            $table->string('seat_no', 10);
            $table->unsignedSmallInteger('row_no')->nullable();
            $table->unsignedSmallInteger('column_no')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exam_invigilations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_subject_id')->index();
            $table->unsignedBigInteger('exam_hall_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('role', 20)->default('invigilator');
            $table->boolean('attended')->default(false);
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admit_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id')->index();
            $table->unsignedBigInteger('enrollment_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('exam_roll_no', 20);
            $table->boolean('is_eligible')->index();
            $table->string('ineligible_reason')->nullable();
            $table->unsignedBigInteger('override_by')->nullable()->index();
            $table->string('override_reason')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedSmallInteger('printed_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exam_id', 'enrollment_id']);
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_subject_id')->index();
            $table->unsignedBigInteger('enrollment_id')->index();
            $table->unsignedBigInteger('section_id')->index();
            $table->decimal('theory_marks', 6, 2)->nullable();
            $table->decimal('practical_marks', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('grade', 5)->nullable();
            $table->string('teacher_remarks')->nullable();
            $table->unsignedBigInteger('entered_by')->nullable()->index();
            $table->string('status', 20)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exam_subject_id', 'enrollment_id']);
        });

        Schema::create('mark_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mark_id')->index();
            $table->decimal('old_theory', 6, 2)->nullable();
            $table->decimal('new_theory', 6, 2)->nullable();
            $table->decimal('old_practical', 6, 2)->nullable();
            $table->decimal('new_practical', 6, 2)->nullable();
            $table->boolean('old_absent')->default(false);
            $table->boolean('new_absent')->default(false);
            $table->text('reason');
            $table->unsignedBigInteger('changed_by')->index();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id')->index();
            $table->unsignedBigInteger('enrollment_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('section_id')->index();
            $table->decimal('total_max', 8, 2);
            $table->decimal('total_obtained', 8, 2);
            $table->decimal('percentage', 5, 2);
            $table->string('grade', 5)->nullable();
            $table->unsignedSmallInteger('position_in_section')->nullable();
            $table->unsignedSmallInteger('position_in_class')->nullable();
            $table->decimal('attendance_percent', 5, 2)->nullable();
            $table->text('class_teacher_remarks')->nullable();
            $table->text('principal_remarks')->nullable();
            $table->string('result', 10)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exam_id', 'enrollment_id']);
        });

        if (! DB::table('permissions')->where('slug', 'manage-exams')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Manage Exams', 'slug' => 'manage-exams', 'group' => 'academics',
                'description' => 'Exam setup, date sheets, admit cards, seating, approving marks and publishing results.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId !== null) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => DB::table('permissions')->where('slug', 'manage-exams')->value('id')],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'manage-exams')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        foreach (['report_cards', 'mark_change_logs', 'marks', 'admit_cards', 'exam_invigilations', 'exam_seatings', 'exam_halls', 'exam_subjects', 'exams', 'exam_terms', 'grading_scale_items', 'grading_scales'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
