<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppraisalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $accountant;

    private Employee $sarah;

    private Employee $nadia;

    private AppraisalCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->accountant = User::where('email', 'accounts@lms.test')->first();
        $this->sarah = Employee::where('user_id', $this->teacher->id)->first();
        $this->nadia = Employee::where('user_id', $this->accountant->id)->first();

        // Sarah (teacher) reports to Nadia; everyone else in Sarah's department falls back to Sarah as head.
        $this->sarah->update(['reports_to_id' => $this->nadia->id]);
        Department::whereKey($this->sarah->department_id)->update(['head_employee_id' => $this->sarah->id]);
        $this->cycle = AppraisalCycle::where('status', 'draft')->first();
    }

    private function launch(): Appraisal
    {
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/appraisal-cycles/{$this->cycle->id}/launch")->assertOk();

        return Appraisal::where('employee_id', $this->sarah->id)->firstOrFail();
    }

    /**
     * @param  array<int, float>  $points
     * @return array<int, array<string, mixed>>
     */
    private function scores(Appraisal $appraisal, string $field, array $points): array
    {
        return $appraisal->scores()->orderBy('id')->get()->values()->map(fn ($row, int $index): array => ['id' => $row->id, $field => $points[$index] ?? null])->all();
    }

    public function test_launch_gives_each_current_employee_an_appraisal_with_their_reviewer_and_criteria(): void
    {
        $appraisal = $this->launch();
        $colleague = Appraisal::where('employee_id', '!=', $this->sarah->id)->whereHas('employee', fn ($query) => $query->where('department_id', $this->sarah->department_id))->first();

        $this->assertSame(Employee::whereIn('status', Employee::CURRENT_STATUSES)->count(), Appraisal::count());
        $this->assertSame($this->nadia->id, $appraisal->reviewer_id);
        $this->assertSame($this->sarah->id, $colleague->reviewer_id);
        $this->assertNull(Appraisal::where('employee_id', $this->nadia->id)->value('reviewer_id'));
        $this->assertSame('self_review', $appraisal->status);
        $this->assertSame(5, $appraisal->scores()->count());
        $this->assertSame(5, Appraisal::where('employee_id', $this->nadia->id)->first()->scores()->count());
        $this->assertSame('open', $this->cycle->refresh()->status);
        $this->assertTrue(UserNotification::where('user_id', $this->accountant->id)->where('event_key', 'appraisal_to_review')->exists());

        $this->postJson("/api/erp/appraisal-cycles/{$this->cycle->id}/launch")->assertOk()->assertJsonPath('message', 'Everyone already has an appraisal in this cycle.');
        $this->deleteJson("/api/erp/appraisal-cycles/{$this->cycle->id}")->assertUnprocessable();
    }

    public function test_self_review_then_weighted_review_then_acknowledgement(): void
    {
        $appraisal = $this->launch();

        $this->actingAs($this->teacher, 'sanctum')->putJson("/api/erp/appraisals/{$appraisal->id}/self-review", ['submit' => true, 'scores' => $this->scores($appraisal, 'self_score', [4, 4])])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.scores.0', 'Score every criterion before submitting.');
        $this->putJson("/api/erp/appraisals/{$appraisal->id}/self-review", ['submit' => true, 'scores' => $this->scores($appraisal, 'self_score', [5, 5, 5, 5, 5])])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.can.self_review', false);

        // Weights: three "all staff" criteria at 15% and the two teaching ones at 30% and 25%.
        $this->actingAs($this->accountant, 'sanctum')->putJson("/api/erp/appraisals/{$appraisal->id}/review", ['scores' => $this->scores($appraisal, 'score', [4, 4, 4, 5, 4]), 'strengths' => 'Strong lessons.'])
            ->assertOk();
        $this->actingAs($this->teacher, 'sanctum')->getJson("/api/erp/appraisals/{$appraisal->id}")
            ->assertOk()
            ->assertJsonPath('data.overall_score', null)
            ->assertJsonPath('data.strengths', null)
            ->assertJsonPath('data.scores.0.score', null);

        $this->actingAs($this->accountant, 'sanctum')->putJson("/api/erp/appraisals/{$appraisal->id}/review", ['submit' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.overall_score', 4.3)
            ->assertJsonPath('data.rating', 'Outstanding');

        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/appraisals/{$appraisal->id}/acknowledge", ['employee_comments' => 'Thank you.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged')
            ->assertJsonPath('data.strengths', 'Strong lessons.');
        $this->assertSame(5.0, $appraisal->refresh()->self_score);
        $this->getJson('/api/erp/my-appraisals')->assertOk()->assertJsonPath('data.mine.0.rating', 'Outstanding');
        $this->get("/api/erp/appraisals/{$appraisal->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_only_the_employee_reviewer_or_hr_can_open_or_score_an_appraisal(): void
    {
        $appraisal = $this->launch();
        $nadiasAppraisal = Appraisal::where('employee_id', $this->nadia->id)->first();

        $this->actingAs($this->teacher, 'sanctum')->getJson("/api/erp/appraisals/{$nadiasAppraisal->id}")->assertForbidden();
        $this->putJson("/api/erp/appraisals/{$appraisal->id}/review", ['scores' => $this->scores($appraisal, 'score', [5])])->assertUnprocessable();
        $this->postJson("/api/erp/appraisal-cycles/{$this->cycle->id}/close")->assertForbidden();
        $this->getJson('/api/erp/my-appraisals')->assertOk()->assertJsonCount(Appraisal::where('reviewer_id', $this->sarah->id)->count(), 'data.to_review');
        $this->assertGreaterThan(0, Appraisal::where('reviewer_id', $this->sarah->id)->count());
    }

    public function test_hr_reassigns_reopens_and_closing_the_cycle_stops_scoring(): void
    {
        $appraisal = $this->launch();
        $reviewer = Employee::where('department_id', $this->sarah->department_id)->where('id', '!=', $this->sarah->id)->first();

        $this->putJson("/api/erp/appraisals/{$appraisal->id}/reviewer", ['reviewer_id' => $this->sarah->id])->assertUnprocessable();
        $this->putJson("/api/erp/appraisals/{$appraisal->id}/reviewer", ['reviewer_id' => $reviewer->id])->assertOk();
        $this->putJson("/api/erp/appraisals/{$appraisal->id}/review", ['submit' => true, 'scores' => $this->scores($appraisal, 'score', [3, 3, 3, 3, 3])])
            ->assertOk()
            ->assertJsonPath('data.rating', 'Meets Expectations');
        $this->postJson("/api/erp/appraisals/{$appraisal->id}/reopen")->assertOk()->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/erp/appraisal-cycles/{$this->cycle->id}/close")->assertOk();
        $this->putJson("/api/erp/appraisals/{$appraisal->id}/review", ['submit' => true])->assertUnprocessable();
        $this->getJson("/api/erp/appraisal-cycles/{$this->cycle->id}")->assertOk()->assertJsonPath('data.by_status.draft', 1);
    }
}
