<?php

namespace Tests\Feature;

use App\Models\CounsellingNote;
use App\Models\Employee;
use App\Models\Lookup;
use App\Models\PerformanceAlert;
use App\Models\PerformanceAlertRule;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CounsellingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounsellingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Student $student;

    private Employee $counsellor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->student = Student::where('first_name', 'John')->where('last_name', 'Doe')->first();
        $this->counsellor = Employee::whereHas('user', fn ($query) => $query->where('email', 'teacher@lms.test'))->first();
        $this->actingAs($this->admin, 'sanctum');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sessionData(array $overrides = []): array
    {
        return [
            'student_id' => $this->student->id, 'session_date' => '2026-10-24', 'counsellor_id' => $this->counsellor->id,
            'concern_id' => Lookup::where('type', 'counselling_concern')->where('name', 'Academic')->value('id'),
            'notes' => 'Struggling with algebra homework.', 'action_plan' => 'Extra class on Tuesdays.', ...$overrides,
        ];
    }

    private function alertFor(Student $student): PerformanceAlert
    {
        $rule = PerformanceAlertRule::create(['name' => 'Low average', 'metric' => 'average_percent', 'operator' => '<', 'threshold' => 50, 'is_active' => true]);

        return PerformanceAlert::create(['enrollment_id' => $student->currentEnrollment->id, 'student_id' => $student->id, 'performance_alert_rule_id' => $rule->id, 'metric_value' => 41, 'status' => 'open']);
    }

    public function test_a_session_answering_an_alert_acknowledges_it_and_is_confidential_by_default(): void
    {
        $alert = $this->alertFor($this->student);

        $note = $this->postJson('/api/erp/counselling-notes', $this->sessionData(['performance_alert_id' => $alert->id, 'follow_up_on' => '2026-10-31']))
            ->assertCreated()
            ->assertJsonPath('data.is_confidential', true)
            ->assertJsonPath('data.alert', 'Low average')
            ->json('data');

        $this->assertSame('acknowledged', $alert->refresh()->status);
        $this->assertSame('Counselling session on 24 Oct 2026', $alert->action_taken);

        $this->postJson('/api/erp/counselling-notes', $this->sessionData(['session_date' => '2026-10-23']))->assertCreated();
        $this->getJson("/api/erp/counselling-notes/{$note['id']}")->assertOk()->assertJsonCount(1, 'data.history');
    }

    public function test_an_alert_of_another_student_is_refused(): void
    {
        $other = Student::whereKeyNot($this->student->id)->whereHas('currentEnrollment')->first();

        $this->postJson('/api/erp/counselling-notes', $this->sessionData(['performance_alert_id' => $this->alertFor($other)->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.performance_alert_id.0', 'That alert belongs to another student.');
    }

    public function test_parents_see_only_shared_sessions_and_staff_without_permission_see_none(): void
    {
        $this->postJson('/api/erp/counselling-notes', $this->sessionData(['notes' => 'Private family matter.']))->assertCreated();
        $this->postJson('/api/erp/counselling-notes', $this->sessionData(['notes' => 'Agreed a homework timetable.', 'is_confidential' => false]))->assertCreated();
        $parent = User::where('email', 'parent@lms.test')->first();

        $this->actingAs($parent, 'sanctum')->getJson("/api/erp/performance/students/{$this->student->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.counselling')
            ->assertJsonPath('data.counselling.0.notes', 'Agreed a homework timetable.');
        $this->get('/dashboard')->assertOk()->assertSee('Agreed a homework timetable.')->assertDontSee('Private family matter.');

        $this->actingAs(User::where('email', 'teacher@lms.test')->first(), 'sanctum')->getJson('/api/erp/counselling-notes/datatable')->assertForbidden();
    }

    public function test_follow_ups_are_reminded_on_the_day_and_can_be_marked_done(): void
    {
        $id = $this->postJson('/api/erp/counselling-notes', $this->sessionData(['session_date' => '2026-10-20', 'follow_up_on' => '2026-10-24']))->json('data.id');

        $this->getJson('/api/erp/counselling-notes/datatable?draw=1&follow_up=due')->assertOk()->assertJsonPath('data.recordsFiltered', 1);
        $this->assertSame(1, app(CounsellingService::class)->sendFollowUpReminders());
        $this->assertTrue(UserNotification::where('user_id', $this->counsellor->user_id)->where('event_key', 'counselling_follow_up')->exists());

        $this->postJson("/api/erp/counselling-notes/{$id}/follow-up-done")->assertOk();
        $this->getJson('/api/erp/counselling-notes/datatable?draw=1&follow_up=due')->assertJsonPath('data.recordsFiltered', 0);

        $this->putJson("/api/erp/counselling-notes/{$id}", $this->sessionData(['session_date' => '2026-10-20', 'follow_up_on' => '2026-11-03']))->assertOk()->assertJsonPath('data.follow_up_done_at', null);

        $this->deleteJson("/api/erp/counselling-notes/{$id}")->assertOk();
        $this->assertSoftDeleted(CounsellingNote::class, ['id' => $id]);
    }
}
