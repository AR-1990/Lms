<?php

namespace Tests\Feature;

use App\Models\AdmitCard;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\ExamHall;
use App\Models\ExamTerm;
use App\Models\FeeHead;
use App\Models\FeeInvoice;
use App\Models\MarkChangeLog;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Section $grade5A;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->grade5A = Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->where('classes.name', 'Grade 5')->where('sections.name', 'A')->select('sections.*')->first();
    }

    /**
     * A Grade 5 exam with two 50-mark papers (pass 20): the teacher's subject first.
     *
     * @return array<string, mixed>
     */
    private function exam(array $overrides = []): array
    {
        $teacherSubject = TeacherAssignment::where('section_id', $this->grade5A->id)->value('subject_id');
        $otherSubject = ClassSubject::where('class_id', $this->grade5A->class_id)->where('subject_id', '!=', $teacherSubject)->value('subject_id');

        $exam = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/exams', [
            'exam_term_id' => ExamTerm::where('name', 'First Term')->value('id'),
            'class_id' => SchoolClass::where('name', 'Grade 5')->value('id'),
            'name' => 'Mid Term — Grade 5',
            'fee_head_id' => FeeHead::where('code', 'EXM')->value('id'),
            'exam_fee_amount' => 150000,
            'instructions' => 'Bring your own stationery.',
            ...$overrides,
        ])->assertCreated()->json('data');

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/erp/exams/{$exam['id']}/date-sheet", ['papers' => [
            ['subject_id' => $teacherSubject, 'exam_date' => '2026-11-03', 'starts_at' => '09:00', 'theory_max_marks' => 50, 'pass_marks' => 20],
            ['subject_id' => $otherSubject, 'exam_date' => '2026-11-04', 'starts_at' => '09:00', 'theory_max_marks' => 50, 'pass_marks' => 20],
        ]])->assertOk()->assertJsonCount(2, 'data.papers');

        return $this->actingAs($this->admin, 'sanctum')->getJson("/api/erp/exams/{$exam['id']}")->json('data');
    }

    /**
     * @param  array<int, float|null>  $marks  per enrolment in roll order
     */
    private function enter(User $user, int $paperId, array $marks, bool $submit = true, ?string $reason = null): TestResponse
    {
        $enrollments = Enrollment::where('section_id', $this->grade5A->id)->orderBy('roll_no')->pluck('id')->values();

        return $this->actingAs($user, 'sanctum')->postJson('/api/erp/marks', [
            'exam_subject_id' => $paperId,
            'section_id' => $this->grade5A->id,
            'submit' => $submit,
            'reason' => $reason,
            'rows' => $enrollments->map(fn (int $id, int $index): array => ['enrollment_id' => $id, 'theory_marks' => $marks[$index] ?? 0])->all(),
        ]);
    }

    public function test_exam_fee_and_attendance_rules_decide_admit_cards_with_admin_override(): void
    {
        $exam = $this->exam();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/status", ['status' => 'scheduled'])->assertOk();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/fee-challans")->assertOk()->assertJsonPath('data.issued', 3);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/fee-challans")->assertOk()->assertJsonPath('data.issued', 0);
        $this->assertSame(3, FeeInvoice::where('exam_id', $exam['id'])->where('invoice_type', 'exam')->count());

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/admit-cards")->assertOk()->assertJsonPath('data.ineligible', 3);

        $card = AdmitCard::where('exam_id', $exam['id'])->first();
        $this->assertSame('fee_due', $card->ineligible_reason);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/admit-cards/{$card->id}/override", ['reason' => 'Fee promised by Friday'])->assertOk();

        $pdf = $this->actingAs($this->admin)->get("/api/erp/exams/{$exam['id']}/admit-cards/pdf");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(1, AdmitCard::where('exam_id', $exam['id'])->where('printed_count', 1)->count());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/exam-papers/'.$exam['papers'][0]['id'].'/seating', ['hall_ids' => ExamHall::pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('data.seated', 1);
    }

    public function test_marks_flow_from_teacher_to_published_report_cards(): void
    {
        $exam = $this->exam(['require_fee_clearance' => false]);
        [$teacherPaper, $otherPaper] = [$exam['papers'][0]['id'], $exam['papers'][1]['id']];

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/status", ['status' => 'scheduled']);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/admit-cards")->assertJsonPath('data.eligible', 3);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/status", ['status' => 'marks_entry'])->assertOk();

        $this->enter($this->teacher, $teacherPaper, [45, 30, 12])->assertOk()->assertJsonPath('data.rows.0.grade', 'A+');
        $this->enter($this->teacher, $otherPaper, [40, 40, 40])->assertUnprocessable();
        $this->enter($this->teacher, $teacherPaper, [60, 30, 12])->assertUnprocessable();
        $this->enter($this->admin, $otherPaper, [40, 35, 30])->assertOk();

        $this->enter($this->teacher, $teacherPaper, [46, 30, 12])->assertUnprocessable()->assertJsonPath('errors.fields.reason.0', 'These marks were already submitted; give a reason for the change.');
        $this->enter($this->teacher, $teacherPaper, [46, 30, 12], true, 'Re-totalled answer sheet')->assertOk();
        $this->assertSame(1, MarkChangeLog::count());

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/status", ['status' => 'review']);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/publish")->assertUnprocessable();

        foreach ([$teacherPaper, $otherPaper] as $paperId) {
            $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/marks/approve', ['exam_subject_id' => $paperId, 'section_id' => $this->grade5A->id])->assertOk();
        }

        $this->enter($this->teacher, $teacherPaper, [47, 30, 12], true, 'One more')->assertUnprocessable()->assertJsonPath('errors.fields.reason.0', 'Approved marks can only be changed by the exam cell.');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/publish")->assertOk()->assertJsonPath('data.status', 'published');

        $first = Enrollment::where('section_id', $this->grade5A->id)->orderBy('roll_no')->first();
        $card = ReportCard::where('enrollment_id', $first->id)->first();
        $this->assertSame(86.0, $card->percentage);
        $this->assertSame('A', $card->grade);
        $this->assertSame(1, $card->position_in_section);
        $this->assertSame('pass', $card->result);
        $this->assertSame('fail', ReportCard::where('position_in_class', 3)->value('result'));

        $tabulation = $this->actingAs($this->admin, 'sanctum')->getJson("/api/erp/exams/{$exam['id']}/tabulation")->assertOk()->json('data');
        $this->assertSame(1, $tabulation['rows'][0]['position']);

        $this->actingAs($this->admin)->get("/api/erp/exams/{$exam['id']}/report-cards")->assertOk()->assertHeader('content-type', 'application/pdf');

        $john = Student::where('first_name', 'John')->first();
        $other = Student::whereKeyNot($john->id)->first();
        $parent = User::where('email', 'parent@lms.test')->first();

        $this->actingAs($parent, 'sanctum')
            ->getJson("/api/erp/exams/results/students/{$john->id}")
            ->assertOk()
            ->assertJsonPath('data.0.exam', 'Mid Term — Grade 5')
            ->assertJsonCount(2, 'data.0.subjects');
        $this->actingAs($parent, 'sanctum')->getJson("/api/erp/exams/results/students/{$other->id}")->assertNotFound();
    }

    public function test_exam_setup_and_screens_need_the_exam_permission(): void
    {
        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/exams', [])->assertForbidden();
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/exam-setup/terms')->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/exam-setup/scales', ['name' => 'Bad', 'items' => [['grade' => 'A', 'min_percent' => 80, 'max_percent' => 70]]])
            ->assertUnprocessable();

        $term = ExamTerm::first();
        $this->exam();
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/erp/exam-setup/terms/'.$term->id)->assertUnprocessable();
    }

    public function test_exam_screens_open_for_the_exam_cell_and_marks_entry_for_teachers(): void
    {
        $exam = $this->exam();
        $this->actingAs($this->teacher)->get(route('admin.exams.marks'))->assertOk()->assertDontSee('Mid Term — Grade 5');
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/exams/{$exam['id']}/status", ['status' => 'scheduled']);

        $this->actingAs($this->admin)->get(route('admin.exams.index'))->assertOk()->assertSee('data-exams', false);
        $this->actingAs($this->admin)->get(route('admin.exams.setup.scales'))->assertOk()->assertSee('data-exam-setup="scales"', false);
        $this->actingAs($this->teacher)->get(route('admin.exams.marks'))->assertOk()->assertSee('data-marks', false)->assertSee('js/resource-screen.js', false)->assertSee('Mid Term — Grade 5');
        $this->actingAs($this->teacher)->get(route('admin.exams.index'))->assertForbidden();
    }
}
