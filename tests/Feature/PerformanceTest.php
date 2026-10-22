<?php

namespace Tests\Feature;

use App\Models\AssessmentSkill;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExamTerm;
use App\Models\Lookup;
use App\Models\Mark;
use App\Models\PerformanceAlert;
use App\Models\PerformanceSnapshot;
use App\Models\ReportCard;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\PerformanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Section $grade5A;

    private ExamTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->grade5A = Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->where('classes.name', 'Grade 5')->where('sections.name', 'A')->select('sections.*')->first();
        $this->term = ExamTerm::where('name', 'First Term')->first();
    }

    /**
     * A published exam where students score 90, 60 and 30 percent in one 100-mark paper.
     */
    private function publishedExam(): void
    {
        $exam = Exam::create(['exam_term_id' => $this->term->id, 'class_id' => $this->grade5A->class_id, 'name' => 'Mid Term', 'status' => 'published']);
        $paper = ExamSubject::create(['exam_id' => $exam->id, 'subject_id' => ClassSubject::where('class_id', $this->grade5A->class_id)->value('subject_id'), 'theory_max_marks' => 100, 'practical_max_marks' => 0]);

        foreach (Enrollment::where('section_id', $this->grade5A->id)->orderBy('roll_no')->get() as $index => $enrollment) {
            $score = [90, 60, 30][$index];
            Mark::create(['exam_subject_id' => $paper->id, 'enrollment_id' => $enrollment->id, 'section_id' => $this->grade5A->id, 'theory_marks' => $score, 'status' => 'approved']);
            ReportCard::create(['exam_id' => $exam->id, 'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'section_id' => $this->grade5A->id, 'total_max' => 100, 'total_obtained' => $score, 'percentage' => $score, 'position_in_section' => $index + 1, 'position_in_class' => $index + 1, 'result' => $score >= 40 ? 'pass' : 'fail']);
        }
    }

    public function test_snapshots_rank_students_and_open_alerts_from_the_rules(): void
    {
        $this->publishedExam();

        $this->assertSame(3, app(PerformanceService::class)->compute($this->term, $this->grade5A->id));

        $weakest = Enrollment::where('section_id', $this->grade5A->id)->orderBy('roll_no')->get()[2];
        $snapshot = PerformanceSnapshot::where('enrollment_id', $weakest->id)->first();
        $this->assertSame(30.0, $snapshot->average_percent);
        $this->assertSame(60.0, $snapshot->class_average_percent);
        $this->assertSame('at_risk', $snapshot->risk_level);
        $this->assertTrue(PerformanceAlert::where('enrollment_id', $weakest->id)->whereHas('rule', fn ($query) => $query->where('metric', 'average_percent'))->exists());

        app(PerformanceService::class)->compute($this->term, $this->grade5A->id);
        $this->assertSame(1, PerformanceAlert::where('enrollment_id', $weakest->id)->whereHas('rule', fn ($query) => $query->where('metric', 'average_percent'))->count());

        $rows = $this->actingAs($this->teacher, 'sanctum')
            ->getJson("/api/erp/performance/overview?section_id={$this->grade5A->id}&exam_term_id={$this->term->id}")
            ->assertOk()
            ->json('data');
        $this->assertSame('at_risk', $rows[0]['risk_level']);
    }

    public function test_parents_see_their_own_childs_tracker_only(): void
    {
        $this->publishedExam();
        app(PerformanceService::class)->compute($this->term);
        $parent = User::where('email', 'parent@lms.test')->first();
        $john = Student::where('first_name', 'John')->first();
        $other = Student::whereKeyNot($john->id)->first();

        $this->actingAs($parent, 'sanctum')
            ->getJson("/api/erp/performance/students/{$john->id}")
            ->assertOk()
            ->assertJsonPath('data.terms.0.term', 'First Term')
            ->assertJsonCount(1, 'data.terms.0.subjects');
        $this->actingAs($parent, 'sanctum')->getJson("/api/erp/performance/students/{$other->id}")->assertNotFound();
        $this->actingAs($parent, 'sanctum')->getJson("/api/erp/performance/overview?section_id={$this->grade5A->id}&exam_term_id={$this->term->id}")->assertForbidden();
    }

    public function test_skills_goals_and_alerts_are_recorded(): void
    {
        $enrollment = Enrollment::where('section_id', $this->grade5A->id)->first();
        $skill = AssessmentSkill::first();
        $rating = Lookup::where('type', 'skill_rating')->where('name', 'Excellent')->value('id');

        $this->actingAs($this->teacher, 'sanctum')
            ->postJson("/api/erp/performance/skills?section_id={$this->grade5A->id}&exam_term_id={$this->term->id}", ['rows' => [['enrollment_id' => $enrollment->id, 'assessment_skill_id' => $skill->id, 'rating_id' => $rating]]])
            ->assertOk()
            ->assertJsonPath('data.saved', 1);

        $goal = $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/performance/goals', ['student_id' => $enrollment->student_id, 'title' => 'Reach 80% in Maths', 'target_value' => 80])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->json('data');
        $this->actingAs($this->teacher, 'sanctum')->putJson("/api/erp/performance/goals/{$goal['id']}", ['status' => 'achieved', 'achieved_value' => 82])->assertOk()->assertJsonPath('data.status', 'achieved');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/erp/performance/students/{$enrollment->student_id}")
            ->assertJsonPath('data.skills.0.rating', 'Excellent')
            ->assertJsonPath('data.goals.0.status', 'achieved');

        $this->publishedExam();
        app(PerformanceService::class)->compute($this->term);
        $alert = PerformanceAlert::first();
        $this->actingAs($this->teacher, 'sanctum')->putJson("/api/erp/performance/alerts/{$alert->id}", ['status' => 'resolved', 'action_taken' => 'Met parents, extra classes'])->assertOk();
        $this->assertNotNull($alert->refresh()->resolved_at);
    }

    public function test_performance_screens_open_for_teachers(): void
    {
        $this->actingAs($this->teacher)->get(route('admin.performance.overview'))->assertOk()->assertSee('data-performance="overview"', false)->assertSee('js/resource-screen.js', false)->assertSee('Grade 5 A');
        $this->actingAs($this->teacher)->get(route('admin.performance.skills'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.exams.setup.rules'))->assertOk()->assertSee('data-exam-setup="rules"', false);
        $this->actingAs(User::where('email', 'parent@lms.test')->first())->get(route('admin.performance.overview'))->assertForbidden();
    }
}
