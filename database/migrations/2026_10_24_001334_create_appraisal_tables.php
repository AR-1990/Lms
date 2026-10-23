<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17c (PLAN.md §14.5): appraisal cycles, weighted criteria (some only for teaching or non-teaching
 * staff), one appraisal per employee with an automatic reviewer, optional self-review, per-criterion scores
 * (reviewer and self) and the employee's acknowledgement. No foreign-key constraints.
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

        Schema::create('appraisal_cycles', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('name', 150);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->boolean('self_review')->default(true);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $audit($table);
        });

        Schema::create('appraisal_criteria', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->decimal('weight_percent', 5, 2)->default(0);
            $table->string('applies_to', 20)->default('all')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $audit($table);
        });

        Schema::create('appraisals', function (Blueprint $table) use ($audit): void {
            $table->id();
            $table->unsignedBigInteger('appraisal_cycle_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('reviewer_id')->nullable()->index();
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('overall_score', 4, 2)->nullable();
            $table->decimal('self_score', 4, 2)->nullable();
            $table->unsignedBigInteger('rating_id')->nullable()->index();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('goals')->nullable();
            $table->text('employee_comments')->nullable();
            $table->timestamp('self_reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $audit($table);
        });

        Schema::create('appraisal_scores', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('appraisal_id')->index();
            $table->unsignedBigInteger('appraisal_criterion_id')->index();
            $table->decimal('score', 4, 2)->nullable();
            $table->string('comment', 500)->nullable();
            $table->decimal('self_score', 4, 2)->nullable();
            $table->string('self_comment', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $now = now();

        if (! DB::table('permissions')->where('slug', 'manage-appraisals')->exists()) {
            DB::table('permissions')->insert(['name' => 'Manage Appraisals', 'slug' => 'manage-appraisals', 'group' => 'hr', 'description' => 'Appraisal cycles and criteria, launching reviews, reassigning reviewers and seeing every result.', 'created_at' => $now, 'updated_at' => $now]);
        }

        $roleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($roleId !== null) {
            DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => DB::table('permissions')->where('slug', 'manage-appraisals')->value('id')], ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'manage-appraisals')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        foreach (['appraisal_scores', 'appraisals', 'appraisal_criteria', 'appraisal_cycles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
