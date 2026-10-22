<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\Lookup;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrRecruitmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /**
     * Set up seeded users, academics and HR master data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    private function lookup(string $type): int
    {
        return Lookup::where('type', $type)->orderBy('sort_order')->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(array $overrides = []): array
    {
        return [
            'first_name' => 'Ayesha',
            'last_name' => 'Siddiqui',
            'gender_id' => $this->lookup('gender'),
            'phone' => '03111234567',
            'email' => 'ayesha@pinnacle.test',
            'cnic' => '35202-1234567-1',
            'designation_id' => Designation::where('name', 'Teacher')->value('id'),
            'employment_type_id' => $this->lookup('employment_type'),
            'joining_date' => '2026-08-01',
            ...$overrides,
        ];
    }

    public function test_seeders_link_staff_logins_to_employee_records(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $employee = Employee::where('user_id', $teacher->id)->first();

        $this->assertNotNull($employee);
        $this->assertTrue($employee->is_teaching_staff);
        $this->assertSame('Senior Teacher', $employee->designation->name);
        $this->assertGreaterThanOrEqual(4, Employee::where('is_teaching_staff', true)->count());
    }

    public function test_hr_endpoints_require_permission(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/employees')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/job-openings', [])->assertForbidden();
    }

    public function test_employee_gets_a_code_service_record_and_optional_portal_login(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/employees', $this->employeePayload([
            'portal_role' => 'teacher',
            'portal_password' => 'secret-pass',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Ayesha Siddiqui')
            ->assertJsonPath('data.is_teaching_staff', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.service_record.0.new_value', 'active');

        $code = $response->json('data.employee_code');
        $this->assertMatchesRegularExpression('/^EMP-\d{4}$/', $code);
        $this->assertSame($code, $response->json('data.portal_login.username'));

        $this->postJson('/api/login', ['email' => $code, 'password' => 'secret-pass'])->assertOk();
    }

    public function test_employee_validation_rejects_bad_cnic_duplicates_and_admin_portal_role(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/employees', $this->employeePayload(['cnic' => '3520212345671', 'portal_role' => 'admin', 'portal_password' => 'secret-pass']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnic', 'portal_role'], 'errors.fields');

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/employees', $this->employeePayload())->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/employees', $this->employeePayload(['phone' => '03119999999']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnic', 'email'], 'errors.fields');
    }

    public function test_designation_change_and_leaving_are_recorded_and_login_is_switched_off(): void
    {
        $employee = Employee::whereNotNull('user_id')->where('is_teaching_staff', true)->first();
        $senior = Designation::where('name', 'Section Head')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/employees/'.$employee->id, [...$this->employeePayload(['email' => $employee->email, 'cnic' => null]), 'designation_id' => $senior->id])
            ->assertOk()
            ->assertJsonPath('data.designation_name', 'Section Head');

        $this->assertDatabaseHas('employee_status_histories', ['employee_id' => $employee->id, 'field' => 'designation', 'old_value' => 'Senior Teacher', 'new_value' => 'Section Head']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/employees/'.$employee->id.'/status', ['status' => 'resigned', 'effective_on' => '2026-12-31', 'remarks' => 'Moved abroad'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resigned')
            ->assertJsonPath('data.exit_date', '2026-12-31');

        $this->assertFalse($employee->user->refresh()->is_active);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/employees/'.$employee->id.'/status', ['status' => 'resigned', 'effective_on' => '2026-12-31'])
            ->assertUnprocessable();
    }

    public function test_department_and_designation_in_use_cannot_be_deleted(): void
    {
        $academics = Department::where('code', 'ACD')->first();
        $teacher = Designation::where('name', 'Teacher')->first();

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/departments/'.$academics->id)->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/designations/'.$teacher->id)->assertUnprocessable();

        $empty = Department::factory()->create();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/departments/'.$empty->id)->assertOk();
    }

    public function test_teacher_assignment_needs_curriculum_subject_and_sets_the_class_teacher(): void
    {
        $session = AcademicSession::where('is_current', true)->first();
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $section = Section::where('class_id', $grade1->id)->where('name', 'A')->first();
        $teacher = Employee::where('is_teaching_staff', true)->first();
        $biology = Subject::where('code', 'BIO')->first();
        $english = Subject::where('code', 'ENG')->first();
        $base = ['employee_id' => $teacher->id, 'academic_session_id' => $session->id, 'section_id' => $section->id];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/teacher-assignments', [...$base, 'subject_id' => $biology->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'subject_id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/teacher-assignments', [...$base, 'subject_id' => $english->id, 'is_class_teacher' => true])
            ->assertCreated()
            ->assertJsonPath('data.class_name', 'Grade 1')
            ->assertJsonPath('data.subject_name', 'English');

        $this->assertSame($teacher->id, $section->refresh()->class_teacher_id);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/teacher-assignments', [...$base, 'subject_id' => $english->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.subject_id.0', 'This subject already has a teacher in this section.');

        $nonTeacher = Employee::where('is_teaching_staff', false)->first();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/teacher-assignments', [...$base, 'employee_id' => $nonTeacher->id, 'subject_id' => Subject::where('code', 'URD')->value('id')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.employee_id.0', 'Choose a teaching staff member.');

        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/employees/'.$teacher->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'employee');
    }

    public function test_candidate_pipeline_enforces_steps_and_hiring_creates_the_employee(): void
    {
        $opening = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/job-openings', [
            'title' => 'Mathematics Teacher',
            'designation_id' => Designation::where('name', 'Teacher')->value('id'),
            'employment_type_id' => $this->lookup('employment_type'),
            'positions' => 1,
            'status' => 'open',
        ])->assertCreated()->json('data.id');

        $application = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/job-applications', [
            'job_opening_id' => $opening,
            'first_name' => 'Usman',
            'last_name' => 'Tariq',
            'phone' => '03211234567',
            'email' => 'usman@candidate.test',
            'gender_id' => $this->lookup('gender'),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.next_statuses', ['shortlisted', 'on_hold', 'rejected', 'withdrawn'])
            ->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/job-applications/{$application}/status", ['status' => 'offered'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.status.0', 'From received the next step can be: shortlisted, on_hold, rejected, withdrawn.');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/job-applications/{$application}/hire", ['joining_date' => '2026-11-01'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'status');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/job-applications/{$application}/status", ['status' => 'shortlisted'])->assertOk();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/job-applications/{$application}/interviews", ['scheduled_at' => '2026-10-28 10:00', 'round_type_id' => $this->lookup('interview_round_type')])
            ->assertCreated()
            ->assertJsonPath('data.round_no', 1);

        $this->assertSame('interview', JobApplication::find($application)->status);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/job-applications/{$application}/status", ['status' => 'offered'])->assertOk();

        $hired = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/job-applications/{$application}/hire", ['joining_date' => '2026-11-01'])
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Usman Tariq')
            ->assertJsonPath('data.designation_name', 'Teacher');

        $employee = Employee::find($hired->json('data.id'));
        $this->assertSame($application, $employee->job_application_id);
        $this->assertSame('hired', JobApplication::find($application)->status);
        $this->assertSame('filled', JobOpening::find($opening)->status);
        $this->assertSame(5, JobApplication::find($application)->statusHistories()->count());

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/erp/job-applications/{$application}")->assertUnprocessable();
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/erp/job-openings/{$opening}")->assertUnprocessable();
    }

    public function test_closed_openings_do_not_take_applications(): void
    {
        $opening = JobOpening::create([
            'title' => 'Old post',
            'designation_id' => Designation::where('name', 'Teacher')->value('id'),
            'status' => 'closed',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/job-applications', ['job_opening_id' => $opening->id, 'first_name' => 'Late', 'phone' => '03001112223'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.job_opening_id.0', 'This opening is not taking applications.');
    }

    public function test_hr_screens_render_for_admin_and_are_forbidden_to_teachers(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        foreach (['employees', 'assignments', 'departments', 'designations', 'candidates', 'openings'] as $screen) {
            $this->actingAs($this->admin)
                ->get(route('admin.hr.'.$screen))
                ->assertOk()
                ->assertSee('data-hr="'.$screen.'"', false)
                ->assertSee('js/admin-hr.js', false);

            $this->actingAs($teacher)->get(route('admin.hr.'.$screen))->assertForbidden();
        }
    }
}
