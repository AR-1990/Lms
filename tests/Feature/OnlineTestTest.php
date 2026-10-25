<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ExamTerm;
use App\Models\FeeHead;
use App\Models\Mark;
use App\Models\OnlineTest;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineTestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $studentUser;

    private TeacherAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->studentUser = User::where('email', 'student@lms.test')->first();
        $sectionId = Student::where('user_id', $this->studentUser->id)->first()->currentEnrollment->section_id;
        $this->assignment = TeacherAssignment::where('section_id', $sectionId)->where('employee_id', Employee::where('user_id', $this->teacher->id)->value('id'))->firstOrFail();
    }

    /**
     * Two questions: 2 marks (right answer "4") and 3 marks (right answer "Lahore").
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Chapter 3 quiz',
            'section_id' => $this->assignment->section_id,
            'subject_id' => $this->assignment->subject_id,
            'opens_at' => '2026-10-22 09:00',
            'closes_at' => '2026-10-22 18:00',
            'duration_minutes' => 20,
            'show_answers' => true,
            'questions' => [
                ['question' => '2 + 2 = ?', 'marks' => 2, 'options' => [['option_text' => '3'], ['option_text' => '4', 'is_correct' => true], ['option_text' => '5']]],
                ['question' => 'Capital of Punjab?', 'marks' => 3, 'options' => [['option_text' => 'Lahore', 'is_correct' => true], ['option_text' => 'Multan']]],
            ],
            ...$overrides,
        ];
    }

    public function test_the_subject_teacher_builds_and_publishes_a_test_and_the_student_is_marked_on_submit(): void
    {
        $test = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/online-tests', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.total_marks', 5)
            ->json('data');

        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/erp/my-online-tests')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/status", ['status' => 'published'])->assertOk();
        $this->assertTrue(UserNotification::where('user_id', $this->studentUser->id)->where('event_key', 'online_test_published')->exists());

        $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/erp/my-online-tests')->assertOk()->assertJsonPath('data.0.is_open', true);
        $started = $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/start")->assertOk()->assertJsonPath('data.seconds_left', 1200);
        $this->assertArrayNotHasKey('is_correct', $started->json('data.questions.0.options.0'));

        $questions = OnlineTest::find($test['id'])->questions()->with('options')->get();
        $right = $questions[0]->options->firstWhere('is_correct', true)->id;
        $wrong = $questions[1]->options->firstWhere('is_correct', false)->id;

        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/submit", ['answers' => [
            ['question_id' => $questions[0]->id, 'option_id' => $right],
            ['question_id' => $questions[1]->id, 'option_id' => $wrong],
        ]])->assertOk()->assertJsonPath('data.score', 2)->assertJsonPath('data.correct_count', 1)->assertJsonPath('data.questions.1.is_correct', false);

        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/start")->assertUnprocessable();
        $this->actingAs($this->studentUser, 'sanctum')->getJson("/api/erp/online-tests/{$test['id']}/review")->assertOk()->assertJsonPath('data.score', 2);

        $this->actingAs($this->teacher, 'sanctum')->getJson("/api/erp/online-tests/{$test['id']}/results")->assertOk()
            ->assertJsonPath('data.rows', fn (array $rows): bool => collect($rows)->firstWhere('status', 'submitted')['score'] === 2);
        $this->actingAs($this->teacher, 'sanctum')->putJson("/api/erp/online-tests/{$test['id']}", $this->payload())->assertUnprocessable()->assertJsonPath('errors.fields.questions.0', 'Students have already attempted this test; its questions, section and subject cannot change.');
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/online-tests/datatable?draw=1')->assertOk()->assertJsonPath('data.data.0.submitted_count', 1);
    }

    public function test_a_test_linked_to_an_exam_paper_writes_scaled_marks(): void
    {
        $exam = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/exams', [
            'exam_term_id' => ExamTerm::where('name', 'First Term')->value('id'),
            'class_id' => SchoolClass::whereKey(Student::where('user_id', $this->studentUser->id)->first()->currentEnrollment->class_id)->value('id'),
            'name' => 'Mid Term',
            'fee_head_id' => FeeHead::where('code', 'EXM')->value('id'),
        ])->assertCreated()->json('data');
        $paper = $this->actingAs($this->admin, 'sanctum')->putJson("/api/erp/exams/{$exam['id']}/date-sheet", ['papers' => [
            ['subject_id' => $this->assignment->subject_id, 'exam_date' => '2026-11-03', 'starts_at' => '09:00', 'theory_max_marks' => 50, 'pass_marks' => 20],
        ]])->assertOk()->json('data.papers.0');

        $test = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/online-tests', $this->payload(['exam_subject_id' => $paper['id']]))->assertCreated()->json('data');
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/status", ['status' => 'published'])->assertOk();
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/start")->assertOk();

        $answers = OnlineTest::find($test['id'])->questions()->with('options')->get()
            ->map(fn ($question): array => ['question_id' => $question->id, 'option_id' => $question->options->firstWhere('is_correct', true)->id])->all();
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/submit", ['answers' => $answers])->assertOk()->assertJsonPath('data.score', 5);

        $mark = Mark::where('exam_subject_id', $paper['id'])->firstOrFail();
        $this->assertSame([50.0, 'submitted'], [$mark->theory_marks, $mark->status]);
    }

    public function test_time_limits_ownership_and_a_correct_answer_are_enforced(): void
    {
        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/online-tests', $this->payload(['questions' => [
            ['question' => 'Pick one', 'options' => [['option_text' => 'A', 'is_correct' => true], ['option_text' => 'B', 'is_correct' => true]]],
        ]]))->assertUnprocessable()->assertJsonPath('errors.fields', fn (array $fields): bool => isset($fields['questions.0.options']));

        $otherSectionId = Section::whereKeyNot($this->assignment->section_id)->value('id');
        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/online-tests', $this->payload(['section_id' => $otherSectionId]))->assertUnprocessable()->assertJsonPath('errors.fields.section_id.0', 'Only the subject teacher of this section can manage this test.');
        $this->actingAs($this->studentUser, 'sanctum')->postJson('/api/erp/online-tests', $this->payload())->assertForbidden();

        $test = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/online-tests', $this->payload())->assertCreated()->json('data');
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/start")->assertNotFound();
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/status", ['status' => 'published'])->assertOk();

        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/start")->assertOk();
        $this->travel(25)->minutes();
        $question = OnlineTest::find($test['id'])->questions()->with('options')->first();
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/submit", ['answers' => [['question_id' => $question->id, 'option_id' => $question->options->firstWhere('is_correct', true)->id]]])
            ->assertOk()
            ->assertJsonPath('data.late', true)
            ->assertJsonPath('data.score', 0);

        $this->actingAs($this->teacher)->get(route('admin.exams.online-tests'))->assertOk()->assertSee('Online Tests');
        $this->actingAs($this->studentUser)->get(route('student.online-tests'))->assertOk()->assertSee('data-student-tests', false);
    }
}
