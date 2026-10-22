<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 (PLAN.md §8): homework with submissions and grading, and the digital daily diary
 * (one page per section per day, entries per subject, private student remarks, parent "signature").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->unsignedBigInteger('teacher_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('assigned_on')->index();
            $table->date('due_on')->nullable()->index();
            $table->unsignedSmallInteger('max_marks')->nullable();
            $table->boolean('allow_submission')->default(false);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('homework_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->text('content')->nullable();
            $table->decimal('marks', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('graded_by')->nullable()->index();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['homework_id', 'student_id']);
        });

        Schema::create('diaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id')->index();
            $table->date('diary_date')->index();
            $table->text('class_teacher_note')->nullable();
            $table->string('status', 20)->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['section_id', 'diary_date']);
        });

        Schema::create('diary_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('diary_id')->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->unsignedBigInteger('teacher_id')->nullable()->index();
            $table->unsignedBigInteger('entry_type_id')->index();
            $table->string('title')->nullable();
            $table->text('details');
            $table->unsignedBigInteger('homework_id')->nullable()->index();
            $table->date('due_on')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('diary_student_remarks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('diary_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('teacher_id')->nullable()->index();
            $table->unsignedBigInteger('remark_type_id')->nullable()->index();
            $table->text('remark');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('diary_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('diary_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('guardian_id')->index();
            $table->timestamp('acknowledged_at');
            $table->text('parent_comment')->nullable();
            $table->string('device', 20)->nullable();
            $table->timestamps();

            $table->unique(['diary_id', 'student_id', 'guardian_id']);
        });

        if (! DB::table('permissions')->where('slug', 'manage-diary')->exists()) {
            DB::table('permissions')->insert([
                'name' => 'Manage Diary & Homework', 'slug' => 'manage-diary', 'group' => 'academics',
                'description' => 'Write the daily diary and homework for sections the teacher teaches, grade submissions.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $permissionId = DB::table('permissions')->where('slug', 'manage-diary')->value('id');

        foreach (DB::table('roles')->whereIn('slug', ['admin', 'teacher'])->pluck('id') as $roleId) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'manage-diary')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        Schema::dropIfExists('diary_acknowledgements');
        Schema::dropIfExists('diary_student_remarks');
        Schema::dropIfExists('diary_entries');
        Schema::dropIfExists('diaries');
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homework');
    }
};
