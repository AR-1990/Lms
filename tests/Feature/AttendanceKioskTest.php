<?php

namespace Tests\Feature;

use App\Models\AttendanceKiosk;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\IdCard;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceKioskTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 08:30'));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->student = Student::where('first_name', 'John')->first();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'student', 'holder_ids' => [$this->student->id]])->assertCreated();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/id-cards/issue', ['holder_type' => 'employee', 'holder_ids' => [Employee::where('user_id', $this->teacher->id)->value('id')]])->assertCreated();
    }

    public function test_a_kiosk_link_marks_students_and_staff_without_sign_in(): void
    {
        $url = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance-kiosks', ['name' => 'Main gate'])->assertCreated()->json('data.url');
        $token = basename($url);
        $this->app['auth']->forgetGuards();

        $this->get($url)->assertOk()->assertSee('/api/kiosk/'.$token, false);
        $this->getJson("/api/kiosk/{$token}")->assertOk()->assertJsonPath('data.name', 'Main gate');

        $studentCard = IdCard::where('holder_type', 'student')->first();
        $this->postJson("/api/kiosk/{$token}/scan", ['code' => route('cards.verify', $studentCard->qr_token)])
            ->assertOk()
            ->assertJsonPath('data.holder_type', 'student')
            ->assertJsonPath('data.action', 'checked_in');
        $this->assertSame('present', StudentAttendance::where('student_id', $this->student->id)->whereDate('date', '2026-10-22')->value('status'));

        $staffCard = IdCard::where('holder_type', 'employee')->first();
        $this->postJson("/api/kiosk/{$token}/scan", ['code' => $staffCard->qr_token])
            ->assertOk()
            ->assertJsonPath('data.status', 'late')
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'checked in late'));
        $staffDay = StaffAttendance::where('employee_id', $staffCard->holder_id)->whereDate('date', '2026-10-22')->first();
        $this->assertSame(['kiosk', $this->admin->id], [$staffDay->source, $staffDay->marked_by]);
        $this->assertSame(2, AttendanceKiosk::first()->scan_count);
    }

    public function test_a_switched_off_or_renewed_link_stops_working_and_other_campus_cards_are_refused(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->student->update(['campus_id' => Campus::factory()->create()->id]);
        $created = $this->postJson('/api/erp/attendance-kiosks', ['name' => 'Branch gate', 'campus_id' => Campus::factory()->create()->id])->assertCreated();
        $id = $created->json('data.kiosk.id');
        $oldToken = basename($created->json('data.url'));

        $this->postJson("/api/kiosk/{$oldToken}/scan", ['code' => IdCard::where('holder_type', 'student')->value('qr_token')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.code.0', 'This card belongs to another campus.');

        $newToken = basename($this->postJson("/api/erp/attendance-kiosks/{$id}/renew")->assertOk()->json('data.url'));
        $this->getJson("/api/kiosk/{$oldToken}")->assertNotFound()->assertJsonPath('success', false);
        $this->getJson("/api/kiosk/{$newToken}")->assertOk();

        $this->putJson("/api/erp/attendance-kiosks/{$id}", ['is_active' => false])->assertOk();
        $this->getJson("/api/kiosk/{$newToken}")->assertNotFound();
        $this->getJson('/api/erp/attendance-kiosks/datatable?draw=1')->assertOk()->assertJsonMissingPath('data.data.0.token_hash');

        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/attendance-kiosks', ['name' => 'Sneaky'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.attendance.kiosks'))->assertOk()->assertSee('Gate Kiosks');
    }

    public function test_students_and_staff_get_their_own_attendance_qr(): void
    {
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/my-attendance-qr')
            ->assertOk()
            ->assertJsonPath('data.holder_type', 'employee')
            ->assertJsonPath('data.card_no', IdCard::where('holder_type', 'employee')->value('card_no'));

        $this->actingAs($this->teacher)->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee('data-my-qr', false);

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/my-attendance-qr')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_the_live_board_shows_gate_arrivals_and_manual_register_marks_together(): void
    {
        $token = basename($this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance-kiosks', ['name' => 'Main gate'])->json('data.url'));
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/kiosk/{$token}/scan", ['code' => IdCard::where('holder_type', 'student')->value('qr_token')])->assertOk();
        $this->postJson("/api/kiosk/{$token}/scan", ['code' => IdCard::where('holder_type', 'employee')->value('qr_token')])->assertOk();

        $enrollment = $this->student->currentEnrollment;
        $live = $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/attendance/live')->assertOk()->json('data');
        $this->assertSame(1, collect($live['students']['sections'])->firstWhere('section_id', $enrollment->section_id)['present']);
        $this->assertSame(1, $live['staff']['in']);
        $this->assertSame(['kiosk'], collect($live['recent'])->pluck('source')->unique()->values()->all());

        // The teacher still marks the register by hand: correcting a gate arrival makes it a manual mark.
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance', ['section_id' => $enrollment->section_id, 'date' => '2026-10-22', 'rows' => [['enrollment_id' => $enrollment->id, 'status' => 'late']]])->assertOk();
        $row = StudentAttendance::where('enrollment_id', $enrollment->id)->first();
        $this->assertSame(['late', 'manual'], [$row->status, $row->source]);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/attendance/live')->assertJsonPath('data.students.totals.late', 1);

        $this->actingAs(User::where('email', 'parent@lms.test')->first(), 'sanctum')->getJson('/api/erp/attendance/live')->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.attendance.live'))->assertOk()->assertSee('data-live-board', false);
    }
}
