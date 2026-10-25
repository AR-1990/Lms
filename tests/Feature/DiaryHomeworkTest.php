<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ClassSubject;
use App\Models\Homework;
use App\Models\Lookup;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiaryHomeworkTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $parent;

    private Section $grade5A;

    private Student $john;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->parent = User::where('email', 'parent@lms.test')->first();
        $this->grade5A = Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->where('classes.name', 'Grade 5')->where('sections.name', 'A')->select('sections.*')->first();
        $this->john = Student::where('first_name', 'John')->first();
    }

    private function subjectId(): int
    {
        return ClassSubject::where('class_id', $this->grade5A->class_id)->value('subject_id');
    }

    private function addHomeworkEntry(): array
    {
        return $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/diary/entries', [
            'section_id' => $this->grade5A->id,
            'diary_date' => '2026-10-22',
            'entry_type_id' => Lookup::where('type', 'diary_entry_type')->where('name', 'Homework')->value('id'),
            'subject_id' => $this->subjectId(),
            'title' => 'Exercise 4.2',
            'details' => 'Questions 1 to 10',
            'due_on' => '2026-10-24',
            'create_homework' => true,
            'max_marks' => 10,
            'allow_submission' => true,
        ])->assertCreated()->json('data');
    }

    public function test_teacher_writes_and_publishes_the_diary_and_the_parent_reads_and_signs_it(): void
    {
        $entry = $this->addHomeworkEntry();
        $this->assertNotNull($entry['homework_id']);

        $page = $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/diary?section_id='.$this->grade5A->id.'&date=2026-10-22')->assertOk()->json('data');
        $this->assertSame('draft', $page['status']);

        $this->actingAs($this->teacher, 'sanctum')
            ->postJson("/api/erp/diaries/{$page['id']}/remarks", ['student_id' => $this->john->id, 'remark' => 'Excellent reading today'])
            ->assertCreated();

        $this->actingAs($this->parent, 'sanctum')->postJson("/api/erp/diaries/{$page['id']}/acknowledge", ['student_id' => $this->john->id])->assertUnprocessable();
        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/diary/students/{$this->john->id}?from=2026-10-01&to=2026-10-31")->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/diaries/{$page['id']}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertTrue(UserNotification::where('user_id', $this->parent->id)->where('event_key', 'diary_published')->exists());

        $this->actingAs($this->parent, 'sanctum')
            ->getJson("/api/erp/diary/students/{$this->john->id}?from=2026-10-01&to=2026-10-31")
            ->assertOk()
            ->assertJsonPath('data.0.entries.0.title', 'Exercise 4.2')
            ->assertJsonPath('data.0.remarks.0.remark', 'Excellent reading today')
            ->assertJsonPath('data.0.signed', false);

        $this->actingAs($this->parent, 'sanctum')->withHeader('X-Platform', 'android')->postJson("/api/erp/diaries/{$page['id']}/acknowledge", ['student_id' => $this->john->id])->assertOk();
        $this->assertDatabaseHas('diary_acknowledgements', ['diary_id' => $page['id'], 'student_id' => $this->john->id, 'device' => 'android']);

        $unsigned = $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/diary?section_id='.$this->grade5A->id.'&date=2026-10-22')->json('data.unsigned');
        $this->assertNotContains($this->john->id, array_column($unsigned, 'student_id'));
        $this->assertCount(2, $unsigned);
    }

    public function test_other_students_remarks_stay_private_and_teachers_only_write_for_their_sections(): void
    {
        $this->addHomeworkEntry();
        $other = Student::where('first_name', '!=', 'John')->whereHas('enrollments', fn ($query) => $query->where('section_id', $this->grade5A->id))->first();
        $page = $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/diary?section_id='.$this->grade5A->id.'&date=2026-10-22')->json('data');
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/diaries/{$page['id']}/remarks", ['student_id' => $other->id, 'remark' => 'Homework incomplete']);
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/diaries/{$page['id']}/publish");

        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/diary/students/{$this->john->id}?from=2026-10-01&to=2026-10-31")->assertJsonCount(0, 'data.0.remarks');
        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/diary/students/{$other->id}")->assertNotFound();

        $grade1A = Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->where('classes.name', 'Grade 1')->where('sections.name', 'A')->select('sections.*')->first();
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/diary?section_id='.$grade1A->id.'&date=2026-10-22')->assertUnprocessable();
    }

    public function test_homework_is_submitted_late_after_the_due_date_and_graded_within_the_maximum(): void
    {
        $homework = Homework::find($this->addHomeworkEntry()['homework_id']);

        $this->travelTo(now()->setDate(2026, 10, 25));
        $this->actingAs($this->parent, 'sanctum')
            ->postJson("/api/erp/homework/{$homework->id}/submit", ['student_id' => $this->john->id, 'content' => 'Answers attached'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'late');

        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/homework/{$homework->id}/grade", ['student_id' => $this->john->id, 'marks' => 12])->assertUnprocessable();
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/homework/{$homework->id}/grade", ['student_id' => $this->john->id, 'marks' => 8, 'feedback' => 'Good'])->assertOk()->assertJsonPath('data.status', 'graded');
        $this->actingAs($this->parent, 'sanctum')->postJson("/api/erp/homework/{$homework->id}/submit", ['student_id' => $this->john->id])->assertUnprocessable();

        $rows = collect($this->actingAs($this->teacher, 'sanctum')->getJson("/api/erp/homework/{$homework->id}/submissions")->assertOk()->json('data'));
        $this->assertSame(8.0, (float) $rows->firstWhere('student_id', $this->john->id)['marks']);
        $this->assertSame('pending', $rows->firstWhere('student_id', '!=', $this->john->id)['status']);

        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/homework')->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    public function test_a_teacher_deleting_a_diary_entry_needs_admin_approval(): void
    {
        $entry = $this->addHomeworkEntry();

        $this->actingAs($this->teacher, 'sanctum')->deleteJson("/api/erp/diary/entries/{$entry['id']}", ['reason' => 'Wrong class'])->assertStatus(202);
        $this->assertSame('pending', ApprovalRequest::where('subject_id', $entry['id'])->value('status'));
        $this->assertDatabaseHas('diary_entries', ['id' => $entry['id'], 'deleted_at' => null]);
    }

    public function test_diary_and_homework_screens_open_for_teachers_but_not_parents(): void
    {
        $this->actingAs($this->teacher)->get(route('admin.diary'))->assertOk()->assertSee('data-diary', false)->assertSee('js/resource-screen.js', false)->assertSee('Grade 5 A');
        $this->actingAs($this->teacher)->get(route('admin.homework'))->assertOk()->assertSee('data-homework', false);
        $this->actingAs($this->parent)->get(route('admin.diary'))->assertForbidden();
    }
}
