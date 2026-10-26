<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ExamTerm;
use App\Models\FeeHead;
use App\Models\Mark;
use App\Models\OnlineTest;
use App\Models\OnlineTestAttempt;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\OnlineTestService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_answers_are_saved_as_the_student_goes_and_an_abandoned_attempt_is_marked_when_time_ends(): void
    {
        $test = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/online-tests', $this->payload())->assertCreated()->json('data');
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/status", ['status' => 'published'])->assertOk();
        $questions = OnlineTest::find($test['id'])->questions()->with('options')->get();
        $right = $questions[1]->options->firstWhere('is_correct', true)->id;

        $this->actingAs($this->studentUser, 'sanctum')->putJson("/api/erp/online-tests/{$test['id']}/answers", ['answers' => [['question_id' => $questions[1]->id, 'option_id' => $right]]])->assertUnprocessable();
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/start")->assertOk();
        $this->actingAs($this->studentUser, 'sanctum')->putJson("/api/erp/online-tests/{$test['id']}/answers", ['answers' => [['question_id' => $questions[1]->id, 'option_id' => $right]]])->assertOk();

        // The connection drops; on another device the saved answer comes back.
        $this->actingAs($this->studentUser, 'sanctum')->postJson("/api/erp/online-tests/{$test['id']}/start")->assertOk()->assertJsonPath("data.saved.{$questions[1]->id}", $right);

        $this->travel(10)->minutes();
        $this->assertSame(0, app(OnlineTestService::class)->finishExpired());
        $this->travel(15)->minutes();
        $this->assertSame(1, app(OnlineTestService::class)->finishExpired());

        $attempt = OnlineTestAttempt::firstOrFail();
        $this->assertSame(['submitted', 3, 1], [$attempt->status, $attempt->score, $attempt->correct_count]);

        $parent = User::where('email', 'parent@lms.test')->first();
        $childId = Student::where('user_id', $this->studentUser->id)->value('id');
        $this->actingAs($parent, 'sanctum')->getJson("/api/erp/my-online-tests?student_id={$childId}")->assertOk()->assertJsonPath('data.0.score', 3);
        $this->actingAs($this->teacher, 'sanctum')->getJson("/api/erp/my-online-tests?student_id={$childId}")->assertNotFound();
    }

    public function test_teachers_bring_questions_from_a_csv_into_the_builder(): void
    {
        $csv = "question*,marks,correct*,option_a*,option_b*,option_c\n\"2 + 3 = ?\",2,B,4,5,6\nCapital of Pakistan?,,a,Islamabad,Lahore,\n";
        $this->actingAs($this->teacher, 'sanctum')
            ->post('/api/erp/imports/online_test_questions', ['file' => UploadedFile::fake()->createWithContent('questions.csv', $csv), 'save' => '0'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.questions.0.marks', 2)
            ->assertJsonPath('data.questions.0.options.1', ['option_text' => '5', 'is_correct' => true])
            ->assertJsonPath('data.questions.1.marks', 1)
            ->assertJsonCount(2, 'data.questions.1.options');

        $this->actingAs($this->teacher, 'sanctum')
            ->post('/api/erp/imports/online_test_questions', ['file' => UploadedFile::fake()->createWithContent('bad.csv', "question*,marks,correct*,option_a*,option_b*\nPick,1,C,x,y\n"), 'save' => '1'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.0.row', 2);
        $this->assertDatabaseCount('online_test_questions', 0);

        $this->actingAs($this->studentUser, 'sanctum')->post('/api/erp/imports/online_test_questions', ['file' => UploadedFile::fake()->createWithContent('q.csv', $csv)], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/imports')->assertOk()->assertJsonMissing(['type' => 'online_test_questions']);
    }
}
