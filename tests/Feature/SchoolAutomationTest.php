<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\FeeHead;
use App\Models\FeeInvoice;
use App\Models\IdCard;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentLeaveRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AdminService;
use App\Services\AttendanceService;
use App\Services\FeeAutomationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SchoolAutomationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 07:45'));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->parent = User::where('email', 'parent@lms.test')->first();
    }

    public function test_students_with_no_mark_become_absent_at_the_set_time_and_a_late_gate_scan_makes_them_late(): void
    {
        $student = Student::where('user_id', User::where('email', 'student@lms.test')->value('id'))->firstOrFail();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student', 'holder_ids' => [$student->id]])->assertCreated();
        $others = Enrollment::where('status', 'active')->where('student_id', '!=', $student->id)->whereNotNull('section_id')->get();
        StudentLeaveRequest::create(['student_id' => $others->first()->student_id, 'requested_by' => $this->parent->id, 'from_date' => '2026-10-22', 'to_date' => '2026-10-22', 'reason' => 'Doctor', 'status' => 'approved']);

        $this->assertSame(0, app(AttendanceService::class)->autoAbsent(), 'off by default');
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/erp/attendance/settings', ['auto_absent_at' => '09:30'])->assertOk()->assertJsonPath('data.auto_absent_at', '09:30');
        $this->assertSame(0, app(AttendanceService::class)->autoAbsent(), 'not yet 09:30');

        $this->travelTo(Carbon::parse('2026-10-22 09:35'));
        $total = Enrollment::where('status', 'active')->whereNotNull('section_id')->count();
        $this->assertSame($total, app(AttendanceService::class)->autoAbsent());
        $this->assertSame(0, app(AttendanceService::class)->autoAbsent(), 'runs once: everybody is marked now');
        $this->assertSame('leave', StudentAttendance::where('student_id', $others->first()->student_id)->value('status'));
        $this->assertSame(['absent', 'auto'], [StudentAttendance::where('student_id', $student->id)->value('status'), StudentAttendance::where('student_id', $student->id)->value('source')]);
        $this->assertTrue(UserNotification::where('user_id', $this->parent->id)->where('event_key', 'absent_alert')->exists());

        // Arrives at 09:50: late, and the family hears that the child arrived.
        $this->travelTo(Carbon::parse('2026-10-22 09:50'));
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance/scan', ['code' => IdCard::where('holder_id', $student->id)->value('qr_token')])->assertOk()->assertJsonPath('data.status', 'late');
        $this->assertSame(['late', 'qr', '09:50:00'], [StudentAttendance::where('student_id', $student->id)->value('status'), StudentAttendance::where('student_id', $student->id)->value('source'), substr(StudentAttendance::where('student_id', $student->id)->value('check_in_at'), 0, 8)]);
        $this->assertTrue(UserNotification::where('user_id', $this->parent->id)->where('event_key', 'student_arrived')->where('body', 'like', '%09:50 AM%')->exists());

        // Sundays are skipped.
        $this->travelTo(Carbon::parse('2026-10-25 10:00'));
        $this->assertSame(0, app(AttendanceService::class)->autoAbsent());
        $this->actingAs(User::where('email', 'teacher@lms.test')->first(), 'sanctum')->putJson('/api/erp/attendance/settings', ['auto_absent_at' => '09:00'])->assertForbidden();
    }

    public function test_families_are_reminded_once_before_a_challan_falls_due(): void
    {
        $studentId = Student::ownIdsFor($this->parent)[0];
        $soon = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/invoices', ['student_id' => $studentId, 'issue_date' => '2026-10-20', 'due_date' => '2026-10-24', 'items' => [['fee_head_id' => FeeHead::where('is_active', true)->value('id'), 'amount' => 350000]]])->assertCreated()->json('data.id');
        $later = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/invoices', ['student_id' => $studentId, 'issue_date' => '2026-10-20', 'due_date' => '2026-11-10', 'items' => [['fee_head_id' => FeeHead::where('is_active', true)->value('id'), 'amount' => 350000]]])->assertCreated()->json('data.id');

        $this->assertSame(0, app(FeeAutomationService::class)->dueSoonReminders(), 'off by default');
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/erp/fees/automation', ['fee_auto_generate_day' => null, 'fee_reminder_days' => 3])->assertOk()->assertJsonPath('data.fee_reminder_days', 3);

        $this->assertSame(1, app(FeeAutomationService::class)->dueSoonReminders());
        $this->assertSame(0, app(FeeAutomationService::class)->dueSoonReminders(), 'once per challan');
        $this->assertNotNull(FeeInvoice::find($soon)->reminder_sent_at);
        $this->assertNull(FeeInvoice::find($later)->reminder_sent_at);
        $this->assertTrue(UserNotification::where('user_id', $this->parent->id)->where('event_key', 'fee_due_soon')->where('body', 'like', '%Rs 3,500%')->exists());
    }

    public function test_the_principal_gets_a_morning_summary(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 10:30'));
        $studentId = Student::ownIdsFor($this->parent)[0];
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/invoices', ['student_id' => $studentId, 'issue_date' => '2026-10-01', 'due_date' => '2026-10-10', 'items' => [['fee_head_id' => FeeHead::where('is_active', true)->value('id'), 'amount' => 250000]]])->assertCreated();

        $figures = app(AdminService::class)->sendDailySummary();

        $this->assertSame(250000, $figures['overdue']);
        $this->assertSame(0, $figures['students_marked']);
        $summary = UserNotification::where('user_id', $this->admin->id)->where('event_key', 'daily_summary')->firstOrFail();
        $this->assertStringContainsString('Overdue fees Rs 2,500', $summary->body);
        $this->assertFalse(UserNotification::where('user_id', $this->parent->id)->where('event_key', 'daily_summary')->exists());
    }
}
