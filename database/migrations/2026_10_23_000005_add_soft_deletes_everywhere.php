<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nothing is permanently deleted: every table whose rows can be removed gets `deleted_at`.
 * Unique indexes on those tables become plain indexes so a deleted row never blocks
 * re-creating it; uniqueness is checked in Form Requests among live rows.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const TABLES = [
        'roles', 'class_subject', 'periods', 'fiscal_years', 'journal_entries', 'journal_lines',
        'teacher_assignments', 'admission_application_guardians', 'guardian_student', 'enrollments',
        'fee_structures', 'fee_structure_items', 'fee_structure_item_months', 'student_concessions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->softDeletes());
            }
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->index('slug');
        });

        Schema::table('class_subject', function (Blueprint $table): void {
            $table->index(['academic_session_id', 'class_id', 'subject_id'], 'class_subject_session_class_subject_index');
            $table->dropUnique(['academic_session_id', 'class_id', 'subject_id']);
        });

        Schema::table('teacher_assignments', function (Blueprint $table): void {
            $table->index(['academic_session_id', 'section_id', 'subject_id'], 'teacher_assignments_session_section_subject_index');
            $table->dropUnique('teacher_assignments_session_section_subject_unique');
        });

        Schema::table('guardian_student', function (Blueprint $table): void {
            $table->index(['guardian_id', 'student_id'], 'guardian_student_pair_index');
            $table->dropUnique(['guardian_id', 'student_id']);
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->index(['student_id', 'academic_session_id'], 'enrollments_student_session_index');
            $table->dropUnique(['student_id', 'academic_session_id']);
        });

        Schema::table('fee_structures', function (Blueprint $table): void {
            $table->index(['academic_session_id', 'class_id'], 'fee_structures_session_class_index');
            $table->dropUnique(['academic_session_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table): void {
            $table->unique(['academic_session_id', 'class_id']);
            $table->dropIndex('fee_structures_session_class_index');
        });
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->unique(['student_id', 'academic_session_id']);
            $table->dropIndex('enrollments_student_session_index');
        });
        Schema::table('guardian_student', function (Blueprint $table): void {
            $table->unique(['guardian_id', 'student_id']);
            $table->dropIndex('guardian_student_pair_index');
        });
        Schema::table('teacher_assignments', function (Blueprint $table): void {
            $table->unique(['academic_session_id', 'section_id', 'subject_id'], 'teacher_assignments_session_section_subject_unique');
            $table->dropIndex('teacher_assignments_session_section_subject_index');
        });
        Schema::table('class_subject', function (Blueprint $table): void {
            $table->unique(['academic_session_id', 'class_id', 'subject_id']);
            $table->dropIndex('class_subject_session_class_subject_index');
        });
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex(['slug']);
            $table->unique('slug');
        });

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
