<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A student's medical record, discipline incidents and achievements, and every certificate issued
 * (bonafide, character) with its own number.
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

        Schema::create('student_medical_records', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('condition_id')->nullable()->index();
            $table->text('allergies')->nullable();
            $table->text('medications')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('doctor_phone', 30)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->string('vision', 50)->nullable();
            $table->date('checked_on')->nullable()->index();
            $table->text('notes')->nullable();
            $audit($table);
        });

        Schema::create('discipline_records', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->date('incident_date')->index();
            $table->unsignedBigInteger('category_id')->index();
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->unsignedBigInteger('reported_by')->nullable()->index();
            $table->boolean('parent_informed')->default(false);
            $table->date('parent_informed_on')->nullable();
            $audit($table);
        });

        Schema::create('student_achievements', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('title');
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('level', 20)->nullable();
            $table->date('achieved_on')->nullable()->index();
            $table->text('description')->nullable();
            $audit($table);
        });

        Schema::create('student_certificates', function (Blueprint $table) use ($audit) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('certificate_type', 20)->index();
            $table->string('certificate_no', 30)->unique();
            $table->date('issued_on')->index();
            $table->string('purpose')->nullable();
            $table->unsignedBigInteger('conduct_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $audit($table);
        });

        $now = now();

        if (Schema::hasTable('number_sequences') && DB::table('number_sequences')->where('type', 'certificate_no')->doesntExist()) {
            DB::table('number_sequences')->insert(['type' => 'certificate_no', 'label' => 'Student certificate', 'prefix' => 'CERT-', 'include_year' => true, 'padding' => 5, 'next_number' => 1, 'reset_yearly' => true, 'current_year' => (int) $now->format('Y'), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_certificates');
        Schema::dropIfExists('student_achievements');
        Schema::dropIfExists('discipline_records');
        Schema::dropIfExists('student_medical_records');
    }
};
