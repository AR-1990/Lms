<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\StaffAttendance;
use App\Models\Substitution;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\TeacherService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubstitutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_periods_of_an_absent_teacher_are_covered_by_a_free_teacher_who_sees_them_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 07:30'));
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@lms.test')->first();
        $teacher = Employee::whereHas('user', fn ($query) => $query->where('email', 'teacher@lms.test'))->firstOrFail();
        $slot = TimetableSlot::where('teacher_id', $teacher->id)->where('day_of_week', 4)->firstOrFail();
        $coverUser = User::factory()->create();
        $cover = Employee::factory()->create(['is_teaching_staff' => true, 'user_id' => $coverUser->id, 'first_name' => 'Hina']);
        $busy = Employee::factory()->create(['is_teaching_staff' => true, 'first_name' => 'Busy']);
        TimetableSlot::create(['section_id' => TimetableSlot::where('id', '!=', $slot->id)->value('section_id') ?? $slot->section_id, 'period_id' => $slot->period_id, 'day_of_week' => 4, 'teacher_id' => $busy->id]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/erp/substitutions?date=2026-10-22')->assertOk()->assertJsonCount(0, 'data.teachers');
        StaffAttendance::create(['employee_id' => $teacher->id, 'date' => '2026-10-22', 'status' => 'absent', 'source' => 'manual']);

        $day = $this->actingAs($admin, 'sanctum')->getJson('/api/erp/substitutions?date=2026-10-22')->assertOk()->json('data');
        $period = collect($day['teachers'][0]['periods'])->firstWhere('timetable_slot_id', $slot->id);
        $free = collect($period['free_teachers'])->pluck('id');
        $this->assertTrue($free->contains($cover->id));
        $this->assertFalse($free->contains($busy->id), 'a teacher with a class in that period is not offered');

        $this->actingAs($admin, 'sanctum')->postJson('/api/erp/substitutions', ['date' => '2026-10-22', 'timetable_slot_id' => $slot->id, 'substitute_teacher_id' => $busy->id])->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->postJson('/api/erp/substitutions', ['date' => '2026-10-22', 'timetable_slot_id' => $slot->id, 'substitute_teacher_id' => $cover->id])->assertCreated();
        $this->assertSame($cover->id, Substitution::firstOrFail()->substitute_teacher_id);
        $this->assertTrue(UserNotification::where('user_id', $coverUser->id)->where('event_key', 'substitution_assigned')->exists());

        $schedule = app(TeacherService::class)->getTeacherDashboard($coverUser)['today_schedule'];
        $this->assertTrue(collect($schedule)->contains('is_cover', true));

        $this->actingAs(User::where('email', 'teacher@lms.test')->first(), 'sanctum')->getJson('/api/erp/substitutions?date=2026-10-22')->assertForbidden();
        $this->actingAs($admin)->get(route('admin.academics.substitutions'))->assertOk()->assertSee('data-substitutions', false);
    }
}
