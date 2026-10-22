<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\IdCard;
use App\Models\Period;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Section $grade5A;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->grade5A = Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->where('classes.name', 'Grade 5')->where('sections.name', 'A')->select('sections.*')->first();
    }

    /**
     * @return array<int, array{enrollment_id: int, status: string}>
     */
    private function rows(string $status = 'present'): array
    {
        return Enrollment::where('section_id', $this->grade5A->id)->pluck('id')->map(fn (int $id): array => ['enrollment_id' => $id, 'status' => $status])->all();
    }

    public function test_the_class_teacher_marks_and_corrects_the_register(): void
    {
        $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/erp/attendance/sheet?section_id='.$this->grade5A->id.'&date=2026-10-22')
            ->assertOk()
            ->assertJsonCount(3, 'data.rows')
            ->assertJsonPath('data.marked', false);

        $rows = $this->rows();
        $rows[1]['status'] = 'absent';

        $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/attendance', ['section_id' => $this->grade5A->id, 'date' => '2026-10-22', 'rows' => $rows])
            ->assertOk()
            ->assertJsonPath('data.present', 2)
            ->assertJsonPath('data.absent', 1);

        $rows[1]['status'] = 'late';
        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/attendance', ['section_id' => $this->grade5A->id, 'date' => '2026-10-22', 'rows' => $rows])->assertOk();

        $this->assertSame(3, StudentAttendance::count());
        $this->assertSame(1, StudentAttendance::where('status', 'late')->count());
        $this->assertDatabaseHas('activity_logs', ['module' => 'attendance', 'action' => 'updated']);
    }

    public function test_teachers_cannot_mark_other_sections_and_future_or_holiday_dates_are_refused(): void
    {
        $grade1A = Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->where('classes.name', 'Grade 1')->where('sections.name', 'A')->select('sections.*')->first();

        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/attendance/sheet?section_id='.$grade1A->id.'&date=2026-10-22')->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/attendance/sheet?section_id='.$grade1A->id.'&date=2026-10-22')->assertOk();

        $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/attendance', ['section_id' => $this->grade5A->id, 'date' => '2026-10-23', 'rows' => $this->rows()])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.date.0', 'Attendance cannot be marked for a future date.');

        CalendarEvent::create(['title' => 'Strike', 'starts_on' => '2026-10-21', 'ends_on' => '2026-10-21', 'is_holiday' => true]);
        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/attendance', ['section_id' => $this->grade5A->id, 'date' => '2026-10-21', 'rows' => $this->rows()])->assertUnprocessable();
    }

    public function test_parent_leave_is_approved_and_prefilled_and_attendance_percentage_is_visible_to_the_parent_only(): void
    {
        $parent = User::where('email', 'parent@lms.test')->first();
        $john = Student::where('first_name', 'John')->first();
        $other = Student::where('first_name', '!=', 'John')->first();

        $leave = $this->actingAs($parent, 'sanctum')
            ->postJson('/api/erp/leave-requests', ['student_id' => $john->id, 'from_date' => '2026-10-22', 'to_date' => '2026-10-23', 'reason' => 'Family wedding'])
            ->assertCreated()
            ->json('data');
        $this->actingAs($parent, 'sanctum')->postJson('/api/erp/leave-requests', ['student_id' => $other->id, 'from_date' => '2026-10-22', 'to_date' => '2026-10-22', 'reason' => 'x'])->assertForbidden();

        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/leave-requests/{$leave['id']}/decision", ['decision' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');

        $sheet = $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/attendance/sheet?section_id='.$this->grade5A->id.'&date=2026-10-22')->json('data.rows');
        $this->assertSame('leave', collect($sheet)->firstWhere('student_id', $john->id)['status']);

        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/attendance', ['section_id' => $this->grade5A->id, 'date' => '2026-10-22', 'rows' => $this->rows()]);

        $this->actingAs($parent, 'sanctum')
            ->getJson("/api/erp/attendance/students/{$john->id}?from=2026-10-01&to=2026-10-31")
            ->assertOk()
            ->assertJsonPath('data.percentage', 100)
            ->assertJsonPath('data.counts.present', 1);
        $this->actingAs($parent, 'sanctum')->getJson("/api/erp/attendance/students/{$other->id}")->assertNotFound();
    }

    public function test_timetable_blocks_teacher_clashes_and_subjects_outside_the_curriculum(): void
    {
        $slot = TimetableSlot::where('section_id', $this->grade5A->id)->whereNotNull('teacher_id')->first();
        $grade5B = Section::where('class_id', $this->grade5A->class_id)->where('name', 'B')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/timetable-slots', ['section_id' => $grade5B->id, 'period_id' => $slot->period_id, 'day_of_week' => $slot->day_of_week, 'subject_id' => $slot->subject_id, 'teacher_id' => $slot->teacher_id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.teacher_id.0', 'This teacher already takes Grade 5 A in this period.');

        $notTaught = Subject::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/timetable-slots', ['section_id' => $this->grade5A->id, 'period_id' => $slot->period_id, 'day_of_week' => 6, 'subject_id' => $notTaught->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.subject_id.0', 'This subject is not in the class curriculum.');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/timetable-slots', ['section_id' => $this->grade5A->id, 'period_id' => Period::where('is_break', false)->value('id'), 'day_of_week' => 6, 'room' => 'Lab'])
            ->assertCreated();

        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/timetable/mine')->assertOk()->assertJsonPath('data.0.section', 'Grade 5 A');
        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/timetable-slots', [])->assertForbidden();
    }

    public function test_public_calendar_events_show_on_the_website_and_holidays_do_not(): void
    {
        $this->get('/')->assertOk()->assertSee('Annual Science &amp; Tech Fair', false)->assertDontSee('Winter Vacation');
        $this->get('/news')->assertOk()->assertSee('Inter-School Sports Gala');
    }

    public function test_attendance_screens_open_for_teachers_and_staff_screen_needs_its_permission(): void
    {
        $this->actingAs($this->teacher)->get(route('admin.attendance.register'))->assertOk()->assertSee('Grade 5 A')->assertDontSee('Grade 1 A');
        $this->actingAs($this->teacher)->get(route('admin.attendance.staff'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.attendance.staff'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.academics.timetable'))->assertOk()->assertSee('data-timetable', false)->assertSee('js/resource-screen.js', false);
        $this->actingAs($this->admin)->get(route('admin.academics.calendar'))->assertOk()->assertSee('data-calendar', false);
    }

    public function test_qr_scan_marks_students_present_and_checks_staff_in_and_out(): void
    {
        $john = Student::where('first_name', 'John')->first();
        $staff = Employee::where('user_id', $this->teacher->id)->first();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student', 'holder_ids' => [$john->id]]);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'employee', 'holder_ids' => [$staff->id]]);
        $studentCard = IdCard::where('holder_type', 'student')->first();
        $staffCard = IdCard::where('holder_type', 'employee')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/attendance/scan', ['code' => route('cards.verify', $studentCard->qr_token)])
            ->assertOk()
            ->assertJsonPath('data.action', 'checked_in');
        $this->assertSame('present', StudentAttendance::where('student_id', $john->id)->value('status'));
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance/scan', ['code' => $studentCard->qr_token])->assertJsonPath('data.action', 'already_marked');

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance/scan', ['code' => $staffCard->qr_token])->assertJsonPath('data.action', 'checked_in');
        $this->travel(8)->hours();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance/scan', ['code' => $staffCard->qr_token])->assertJsonPath('data.action', 'checked_out');
        $this->assertNotNull(StaffAttendance::where('employee_id', $staff->id)->value('check_out_at'));

        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/attendance/scan', ['code' => $staffCard->qr_token])->assertUnprocessable();
        $studentCard->update(['status' => 'lost']);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance/scan', ['code' => $studentCard->qr_token])->assertUnprocessable()->assertJsonPath('errors.fields.code.0', 'This card is lost.');
        $this->actingAs($this->admin)->get(route('admin.attendance.scan'))->assertOk()->assertSee('data-scan-url', false);
    }
}
