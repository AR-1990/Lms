<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Models\WorkShift;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 15:00'));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = Employee::whereHas('user', fn ($query) => $query->where('email', 'teacher@lms.test'))->firstOrFail();
        $this->actingAs($this->admin, 'sanctum');
    }

    public function test_check_in_after_the_grace_minutes_is_late_by_the_employee_shift(): void
    {
        $this->assertNotNull(WorkShift::where('is_default', true)->first());

        $this->postJson('/api/erp/attendance/staff', ['date' => '2026-10-22', 'rows' => [['employee_id' => $this->teacher->id, 'status' => 'present', 'check_in_at' => '08:25']]])->assertOk();
        $row = StaffAttendance::where('employee_id', $this->teacher->id)->whereDate('date', '2026-10-22')->first();
        $this->assertSame(['late', 25], [$row->status, $row->late_minutes]);

        $this->postJson('/api/erp/attendance/staff', ['date' => '2026-10-21', 'rows' => [['employee_id' => $this->teacher->id, 'status' => 'present', 'check_in_at' => '08:08']]])->assertOk();
        $this->assertSame('present', StaffAttendance::where('employee_id', $this->teacher->id)->whereDate('date', '2026-10-21')->value('status'));

        $evening = $this->postJson('/api/erp/work-shifts', ['name' => 'Evening', 'starts_at' => '13:00', 'ends_at' => '19:00', 'grace_minutes' => 5])->assertCreated()->json('data.id');
        $this->teacher->update(['work_shift_id' => $evening]);
        $this->postJson('/api/erp/attendance/staff', ['date' => '2026-10-20', 'rows' => [['employee_id' => $this->teacher->id, 'status' => 'present', 'check_in_at' => '12:58']]])->assertOk();
        $this->assertSame('present', StaffAttendance::where('employee_id', $this->teacher->id)->whereDate('date', '2026-10-20')->value('status'));

        $this->deleteJson("/api/erp/work-shifts/{$evening}")->assertUnprocessable();
        $this->getJson('/api/erp/work-shifts/datatable?per_page=10')->assertOk()->assertJsonPath('data.meta.total', 2);
        $this->actingAs($this->admin)->get('/admin/hr/shifts')->assertOk()->assertSee('Shifts');
    }

    public function test_biometric_punches_become_attendance_days(): void
    {
        $code = $this->teacher->employee_code;
        $csv = "employee_code,date,time\n{$code},2026-10-19,08:31\n{$code},2026-10-19,14:35\n{$code},19/10/2026,11:00 AM\n";

        $this->post('/api/erp/imports/staff_attendance', ['file' => UploadedFile::fake()->createWithContent('punches.csv', $csv), 'save' => '1'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 3)
            ->assertJsonPath('data.days.saved', 1);

        $day = StaffAttendance::where('employee_id', $this->teacher->id)->whereDate('date', '2026-10-19')->firstOrFail();
        $this->assertSame(['late', 31, '08:31:00', '14:35:00', 'biometric'], [$day->status, $day->late_minutes, substr($day->check_in_at, 0, 8), substr($day->check_out_at, 0, 8), $day->source]);

        $this->post('/api/erp/imports/staff_attendance', ['file' => UploadedFile::fake()->createWithContent('bad.csv', "employee_code,date,time\nNOPE,2026-10-19,8:00\n"), 'save' => '0'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.0.row', 2);
    }
}
