<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 (PLAN.md §20): co-scholastic skills, goals, pre-computed performance snapshots
 * (term and subject level) and at-risk alerts from rules the school defines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('skill_group_id')->nullable()->index();
            $table->unsignedBigInteger('applies_to_wing_id')->nullable()->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('skill_assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id')->index();
            $table->unsignedBigInteger('exam_term_id')->index();
            $table->unsignedBigInteger('assessment_skill_id')->index();
            $table->unsignedBigInteger('rating_id')->nullable()->index();
            $table->string('comment')->nullable();
            $table->unsignedBigInteger('assessed_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['enrollment_id', 'exam_term_id', 'assessment_skill_id'], 'skill_assessments_lookup_index');
        });

        Schema::create('student_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('title');
            $table->decimal('target_value', 6, 2)->nullable();
            $table->date('target_date')->nullable();
            $table->unsignedBigInteger('set_by')->nullable()->index();
            $table->string('status', 20)->index();
            $table->decimal('achieved_value', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('section_id')->index();
            $table->string('period_type', 10);
            $table->date('period_start');
            $table->unsignedBigInteger('exam_term_id')->nullable()->index();
            $table->decimal('attendance_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->decimal('average_percent', 5, 2)->nullable();
            $table->decimal('class_average_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank_in_section')->nullable();
            $table->decimal('homework_completion_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('late_submissions')->default(0);
            $table->unsignedSmallInteger('appreciation_count')->default(0);
            $table->unsignedSmallInteger('discipline_count')->default(0);
            $table->string('risk_level', 10)->index();
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['enrollment_id', 'period_type', 'period_start'], 'performance_snapshots_period_index');
        });

        Schema::create('subject_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('performance_snapshot_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->decimal('average_percent', 5, 2)->nullable();
            $table->decimal('highest_percent', 5, 2)->nullable();
            $table->decimal('class_average_percent', 5, 2)->nullable();
            $table->decimal('change_from_previous', 6, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('performance_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('metric', 30);
            $table->string('operator', 2);
            $table->decimal('threshold', 6, 2);
            $table->boolean('notify_class_teacher')->default(true);
            $table->boolean('notify_parents')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('performance_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('performance_alert_rule_id')->index();
            $table->unsignedBigInteger('exam_term_id')->nullable()->index();
            $table->decimal('metric_value', 6, 2)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('status', 20)->index();
            $table->text('action_taken')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        if (! DB::table('permissions')->where('slug', 'view-performance')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'View Performance', 'slug' => 'view-performance', 'group' => 'academics',
                'description' => 'Student performance tracker: snapshots, skills, goals and at-risk alerts.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $permissionId = DB::table('permissions')->where('slug', 'view-performance')->value('id');

        foreach (DB::table('roles')->whereIn('slug', ['admin', 'teacher'])->pluck('id') as $roleId) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'view-performance')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        foreach (['performance_alerts', 'performance_alert_rules', 'subject_performance_snapshots', 'performance_snapshots', 'student_goals', 'skill_assessments', 'assessment_skills'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
