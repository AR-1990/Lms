<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\Homework;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentLeaveRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The extra API the mobile app needs: teacher pickers, one child's homework with its own submission,
 * and one child's leave requests.
 */
class MobileAppApiTest extends TestCase
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

    public function test_a_teacher_gets_their_own_sections_subjects_and_open_exams_for_the_pickers(): void
    {
        $data = $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/teacher/options')->assertOk()->json('data');

        $section = collect($data['sections'])->firstWhere('id', $this->grade5A->id);
        $this->assertNotNull($section, 'the class teacher sees their section');
        $this->assertSame($this->grade5A->class_id, $section['class_id']);
        $this->assertEqualsCanonicalizing(
            ClassSubject::where('class_id', $this->grade5A->class_id)->pluck('subject_id')->all(),
            array_column($section['subjects'], 'id'),
        );
        $this->assertSame($this->grade5A->class_teacher_id === Employee::where('user_id', $this->teacher->id)->value('id'), $section['is_class_teacher']);
        foreach ($data['exams'] as $exam) {
            $this->assertContains($exam['class_id'], array_column($data['sections'], 'class_id'), 'only exams of the teacher\'s classes');
        }

        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/teacher/options')->assertForbidden();
    }

    public function test_a_parent_sees_one_childs_homework_with_that_childs_own_submission(): void
    {
        $homework = Homework::create([
            'section_id' => $this->grade5A->id, 'subject_id' => ClassSubject::where('class_id', $this->grade5A->class_id)->value('subject_id'),
            'title' => 'Exercise 4.2', 'assigned_on' => '2026-10-22', 'due_on' => '2026-10-24', 'max_marks' => 10, 'allow_submission' => true,
        ]);

        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/homework?student_id='.$this->john->id)->assertOk()
            ->assertJsonPath('data.items.0.id', $homework->id)
            ->assertJsonPath('data.items.0.my_submission', null);

        $this->actingAs($this->parent, 'sanctum')->postJson("/api/erp/homework/{$homework->id}/submit", ['student_id' => $this->john->id, 'content' => 'Done all ten'])->assertCreated();

        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/homework?student_id='.$this->john->id)
            ->assertJsonPath('data.items.0.my_submission.status', 'submitted')
            ->assertJsonPath('data.items.0.my_submission.content', 'Done all ten');

        $other = Student::whereKeyNot($this->john->id)->whereNotIn('id', Student::ownIdsFor($this->parent))->first();
        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/homework?student_id='.$other->id)->assertOk()->assertJsonCount(0, 'data.items');
        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/homework')->assertOk()->assertJsonMissingPath('data.items.0.my_submission');
    }

    public function test_leave_requests_can_be_filtered_to_one_child_but_never_to_someone_elses(): void
    {
        $this->actingAs($this->parent, 'sanctum')->postJson('/api/erp/leave-requests', ['student_id' => $this->john->id, 'from_date' => '2026-10-23', 'to_date' => '2026-10-23', 'reason' => 'Doctor visit'])->assertCreated();
        $other = Student::whereNotIn('id', Student::ownIdsFor($this->parent))->first();
        StudentLeaveRequest::create(['student_id' => $other->id, 'from_date' => '2026-10-23', 'to_date' => '2026-10-23', 'reason' => 'Wedding', 'status' => 'pending', 'requested_by' => $this->teacher->id]);

        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/leave-requests?student_id='.$this->john->id)->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.reason', 'Doctor visit');
        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/leave-requests?student_id='.$other->id)->assertOk()->assertJsonCount(0, 'data.items');
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/leave-requests?student_id='.$other->id)->assertOk()->assertJsonPath('data.items.0.reason', 'Wedding');
    }
}
