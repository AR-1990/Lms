<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modules 2–3 — Admissions and student management (PLAN.md §5–6). No FK constraints.
     */
    public function up(): void
    {
        Schema::create('admission_inquiries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('inquiry_no', 30)->unique();
            $table->date('inquiry_date')->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('parent_name');
            $table->string('phone', 30)->index();
            $table->string('email')->nullable();
            $table->string('child_name')->nullable();
            $table->date('child_dob')->nullable();
            $table->unsignedBigInteger('class_sought_id')->nullable()->index();
            $table->unsignedBigInteger('academic_session_id')->nullable()->index();
            $table->string('previous_school')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 30)->default('new')->index();
            $table->date('follow_up_on')->nullable()->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->unsignedBigInteger('closed_reason_id')->nullable()->index();
            $table->unsignedBigInteger('application_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('application_no', 30)->unique();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->unsignedBigInteger('class_applied_id')->index();
            $table->date('applied_on')->index();
            $table->string('first_name', 100)->index();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->unsignedBigInteger('gender_id')->index();
            $table->date('date_of_birth');
            $table->string('place_of_birth')->nullable();
            $table->string('b_form_no', 20)->nullable()->index();
            $table->unsignedBigInteger('nationality_id')->nullable()->index();
            $table->unsignedBigInteger('religion_id')->nullable()->index();
            $table->unsignedBigInteger('blood_group_id')->nullable()->index();
            $table->string('photo_path')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('previous_school')->nullable();
            $table->string('previous_class', 50)->nullable();
            $table->string('previous_result', 50)->nullable();
            $table->text('reason_for_leaving')->nullable();
            $table->boolean('has_sibling_in_school')->default(false);
            $table->unsignedBigInteger('sibling_student_id')->nullable()->index();
            $table->text('special_needs')->nullable();
            $table->text('medical_notes')->nullable();
            $table->boolean('transport_required')->default(false);
            $table->unsignedBigInteger('inquiry_id')->nullable()->index();
            $table->string('status', 30)->default('submitted')->index();
            $table->date('decision_on')->nullable();
            $table->unsignedBigInteger('decision_by')->nullable()->index();
            $table->unsignedBigInteger('rejection_reason_id')->nullable()->index();
            $table->date('offer_expires_on')->nullable();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admission_application_guardians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id')->index();
            $table->unsignedBigInteger('relation_id')->index();
            $table->string('name');
            $table->string('cnic', 15)->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('occupation_id')->nullable()->index();
            $table->string('employer')->nullable();
            $table->bigInteger('monthly_income')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->unsignedBigInteger('existing_guardian_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admission_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id')->index();
            $table->dateTime('scheduled_at');
            $table->string('venue')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->smallInteger('max_marks')->nullable();
            $table->decimal('obtained_marks', 6, 2)->nullable();
            $table->string('result', 30)->nullable();
            $table->unsignedBigInteger('examiner_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admission_interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id')->index();
            $table->dateTime('scheduled_at');
            $table->string('mode', 20)->default('in_person');
            $table->string('venue_or_link')->nullable();
            $table->unsignedTinyInteger('overall_rating')->nullable();
            $table->string('recommendation', 30)->nullable();
            $table->text('feedback')->nullable();
            $table->boolean('attended')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admission_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id')->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('admission_no', 30)->unique();
            $table->date('admission_date')->index();
            $table->unsignedBigInteger('application_id')->nullable()->index();
            $table->string('first_name', 100)->index();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable()->index();
            $table->unsignedBigInteger('gender_id')->index();
            $table->date('date_of_birth');
            $table->string('place_of_birth')->nullable();
            $table->string('b_form_no', 20)->nullable()->index();
            $table->unsignedBigInteger('nationality_id')->nullable()->index();
            $table->unsignedBigInteger('religion_id')->nullable()->index();
            $table->unsignedBigInteger('blood_group_id')->nullable()->index();
            $table->string('mother_tongue', 50)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('permanent_address')->nullable();
            $table->unsignedBigInteger('house_id')->nullable()->index();
            $table->string('previous_school')->nullable();
            $table->string('previous_class', 50)->nullable();
            $table->string('current_status', 30)->default('active')->index();
            $table->date('left_on')->nullable();
            $table->unsignedBigInteger('leaving_reason_id')->nullable()->index();
            $table->string('slc_no', 30)->nullable();
            $table->date('slc_issued_on')->nullable();
            $table->boolean('is_staff_child')->default(false);
            $table->unsignedBigInteger('staff_parent_id')->nullable()->index();
            $table->text('special_needs')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('cnic', 15)->nullable()->index();
            $table->string('phone', 30)->index();
            $table->string('alt_phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('occupation_id')->nullable()->index();
            $table->string('employer')->nullable();
            $table->string('designation')->nullable();
            $table->bigInteger('monthly_income')->nullable();
            $table->unsignedBigInteger('education_id')->nullable()->index();
            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guardian_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('relation_id')->index();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_fee_payer')->default(false);
            $table->boolean('can_pick_up')->default(true);
            $table->boolean('lives_with_student')->default(true);
            $table->timestamps();
            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('academic_session_id')->index();
            $table->unsignedBigInteger('class_id')->index();
            $table->unsignedBigInteger('section_id')->nullable()->index();
            $table->string('roll_no', 20)->nullable();
            $table->date('enrolled_on');
            $table->string('status', 30)->default('active')->index();
            $table->unsignedBigInteger('promoted_to_enrollment_id')->nullable()->index();
            $table->string('result_status', 30)->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->unique(['student_id', 'academic_session_id']);
        });

        Schema::create('student_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->unsignedBigInteger('reason_id')->nullable()->index();
            $table->date('effective_on');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['student_status_histories', 'enrollments', 'guardian_student', 'guardians', 'students', 'admission_status_histories', 'admission_interviews', 'admission_tests', 'admission_application_guardians', 'admission_applications', 'admission_inquiries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
