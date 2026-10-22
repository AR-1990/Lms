<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (PLAN.md §4, §7, §14): weekly timetable, school calendar (also the website's events),
 * student and staff attendance, and student leave requests.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{name: string, slug: string, group: string, description: string}>
     */
    private array $permissions = [
        ['name' => 'Manage Timetable', 'slug' => 'manage-timetable', 'group' => 'academics', 'description' => 'Weekly class timetables (teacher clash checks).'],
        ['name' => 'Manage Calendar', 'slug' => 'manage-calendar', 'group' => 'academics', 'description' => 'Holidays, exams, meetings and public events.'],
        ['name' => 'Manage Staff Attendance', 'slug' => 'manage-staff-attendance', 'group' => 'hr', 'description' => 'Mark daily staff attendance.'],
    ];

    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id')->index();
            $table->unsignedBigInteger('period_id')->index();
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->unsignedBigInteger('teacher_id')->nullable()->index();
            $table->string('room', 50)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['section_id', 'day_of_week', 'period_id']);
            $table->index(['teacher_id', 'day_of_week', 'period_id']);
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campus_id')->nullable()->index();
            $table->string('title');
            $table->unsignedBigInteger('event_type_id')->nullable()->index();
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue')->nullable();
            $table->boolean('is_holiday')->default(false)->index();
            $table->boolean('is_public')->default(false)->index();
            $table->unsignedBigInteger('audience_role_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('enrollment_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('section_id')->index();
            $table->date('date')->index();
            $table->string('status', 20)->index();
            $table->time('check_in_at')->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('marked_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['enrollment_id', 'date']);
            $table->index(['section_id', 'date']);
        });

        Schema::create('student_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->date('from_date')->index();
            $table->date('to_date')->index();
            $table->text('reason');
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->date('date')->index();
            $table->time('check_in_at')->nullable();
            $table->time('check_out_at')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->string('source', 20)->default('manual');
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('marked_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });

        foreach ($this->permissions as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert([...$permission, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($adminRoleId !== null) {
            foreach (DB::table('permissions')->whereIn('slug', array_column($this->permissions, 'slug'))->pluck('id') as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $adminRoleId, 'permission_id' => $permissionId], ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', array_column($this->permissions, 'slug'))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('student_leave_requests');
        Schema::dropIfExists('student_attendances');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('timetable_slots');
    }
};
