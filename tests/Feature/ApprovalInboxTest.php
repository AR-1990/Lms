<?php

namespace Tests\Feature;

use App\Models\ConcessionType;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Student;
use App\Models\StudentConcession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApprovalInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_gathers_every_waiting_decision_with_a_working_action(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@lms.test')->first();
        $parent = User::where('email', 'parent@lms.test')->first();
        $john = Student::where('first_name', 'John')->first();
        $teacher = Employee::whereHas('user', fn ($query) => $query->where('email', 'teacher@lms.test'))->first();

        $count = fn (string $key): int => (int) collect($this->actingAs($admin, 'sanctum')->getJson('/api/erp/approvals/inbox')->json('data.sections'))->firstWhere('key', $key)['count'];
        $before = ['concessions' => $count('concessions'), 'loans' => $count('loans'), 'student_leave' => $count('student_leave')];

        $concession = StudentConcession::create(['student_id' => $john->id, 'concession_type_id' => ConcessionType::where('requires_approval', true)->value('id'), 'calculation' => 'percent', 'value' => 2500, 'valid_from' => '2026-10-01', 'status' => 'pending']);
        EmployeeLoan::create(['employee_id' => $teacher->id, 'amount' => 2000000, 'instalments' => 10, 'instalment_amount' => 200000, 'start_month' => '2026-11-01', 'outstanding' => 0, 'status' => 'pending', 'reason' => 'Family need']);
        $this->actingAs($parent, 'sanctum')->postJson('/api/erp/leave-requests', ['student_id' => $john->id, 'from_date' => '2026-10-23', 'to_date' => '2026-10-23', 'reason' => 'Doctor visit'])->assertCreated();

        $inbox = $this->actingAs($admin, 'sanctum')->getJson('/api/erp/approvals/inbox')->assertOk()->assertJsonPath('success', true);
        $sections = collect($inbox->json('data.sections'))->keyBy('key');
        foreach ($before as $key => $was) {
            $this->assertSame($was + 1, $sections[$key]['count'], $key);
        }
        $this->assertGreaterThanOrEqual(3, $inbox->json('data.total'));

        $approve = collect($sections['concessions']['items'])->firstWhere('id', $concession->id)['approve'];
        $this->postJson($approve['url'], $approve['body'])->assertOk();
        $this->assertSame('approved', $concession->fresh()->status);

        $loan = EmployeeLoan::where('employee_id', $teacher->id)->where('reason', 'Family need')->firstOrFail();
        $reject = collect(collect($this->getJson('/api/erp/approvals/inbox')->json('data.sections'))->firstWhere('key', 'loans')['items'])->firstWhere('id', $loan->id)['reject'];
        $this->postJson($reject['url'], [...$reject['body'], $reject['reason_field'] => 'Not this month'])->assertOk();
        $this->assertSame('rejected', $loan->fresh()->status);

        $this->assertSame($before['concessions'], $count('concessions'));

        $this->actingAs($parent, 'sanctum')->getJson('/api/erp/approvals/inbox')->assertOk()->assertJsonPath('data.total', 0);
        $this->actingAs($admin)->get('/approvals-inbox')->assertOk()->assertSee('Approvals Inbox');
    }
}
