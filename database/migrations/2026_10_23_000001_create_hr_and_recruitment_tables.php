<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modules 9–10 — Recruitment and employee management (PLAN.md §12–13).
     * No FK constraints: references are indexed columns. Money in paisa.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('name', 100)->index();
            $table->string('code', 20)->nullable()->index();
            $table->unsignedBigInteger('head_employee_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('grade_id')->nullable()->index();
            $table->boolean('is_teaching')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('employee_code', 30)->unique();
            $table->unsignedBigInteger('job_application_id')->nullable()->index();
            $table->string('first_name', 100)->index();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable()->index();
            $table->string('father_or_spouse_name')->nullable();
            $table->unsignedBigInteger('gender_id')->index();
            $table->date('date_of_birth')->nullable();
            $table->string('cnic', 15)->nullable()->index();
            $table->date('cnic_expiry')->nullable();
            $table->unsignedBigInteger('marital_status_id')->nullable()->index();
            $table->unsignedBigInteger('religion_id')->nullable()->index();
            $table->unsignedBigInteger('blood_group_id')->nullable()->index();
            $table->unsignedBigInteger('nationality_id')->nullable()->index();
            $table->string('photo_path')->nullable();
            $table->string('phone', 30)->index();
            $table->string('alt_phone', 30)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('personal_email')->nullable();
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('designation_id')->index();
            $table->unsignedBigInteger('employment_type_id')->index();
            $table->unsignedBigInteger('reports_to_id')->nullable()->index();
            $table->date('joining_date')->index();
            $table->date('probation_end_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->boolean('is_teaching_staff')->default(false)->index();
            $table->string('ntn', 30)->nullable();
            $table->string('eobi_no', 30)->nullable();
            $table->unsignedBigInteger('bank_id')->nullable()->index();
            $table->string('bank_account_title')->nullable();
            $table->string('bank_account_no', 50)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->date('exit_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('field', 30);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->date('effective_on');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->unsignedBigInteger('section_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->boolean('is_class_teacher')->default(false);
            $table->unsignedTinyInteger('periods_per_week')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->unique(['academic_session_id', 'section_id', 'subject_id'], 'teacher_assignments_session_section_subject_unique');
        });

        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('title');
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('designation_id')->index();
            $table->unsignedBigInteger('employment_type_id')->nullable()->index();
            $table->smallInteger('positions')->default(1);
            $table->string('subjects_note')->nullable();
            $table->unsignedBigInteger('min_qualification_id')->nullable()->index();
            $table->decimal('min_experience_years', 4, 1)->nullable();
            $table->bigInteger('salary_min')->nullable();
            $table->bigInteger('salary_max')->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable()->index();
            $table->boolean('is_published')->default(false)->index();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('application_no', 30)->unique();
            $table->unsignedBigInteger('job_opening_id')->index();
            $table->string('first_name', 100)->index();
            $table->string('last_name', 100)->nullable();
            $table->string('cnic', 15)->nullable()->index();
            $table->unsignedBigInteger('gender_id')->nullable()->index();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30)->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedBigInteger('highest_qualification_id')->nullable()->index();
            $table->text('qualification_details')->nullable();
            $table->decimal('experience_years', 4, 1)->nullable();
            $table->string('current_employer')->nullable();
            $table->bigInteger('current_salary')->nullable();
            $table->bigInteger('expected_salary')->nullable();
            $table->smallInteger('notice_period_days')->nullable();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->unsignedBigInteger('referred_by_employee_id')->nullable()->index();
            $table->string('photo_path')->nullable();
            $table->string('status', 30)->default('received')->index();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedBigInteger('rejection_reason_id')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('job_application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_application_id')->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('job_interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_application_id')->index();
            $table->unsignedTinyInteger('round_no');
            $table->unsignedBigInteger('round_type_id')->nullable()->index();
            $table->dateTime('scheduled_at')->index();
            $table->string('mode', 20)->default('in_person');
            $table->string('venue_or_link')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->decimal('overall_score', 4, 1)->nullable();
            $table->string('recommendation', 20)->nullable();
            $table->text('summary')->nullable();
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
        foreach (['job_interviews', 'job_application_status_histories', 'job_applications', 'job_openings', 'teacher_assignments', 'employee_status_histories', 'employees', 'designations', 'departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
