<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ActivityLog;
use App\Models\ConcessionType;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamTerm;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentConcession;
use App\Models\StudentTransport;
use App\Models\TransportStop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicSession $from;

    private AcademicSession $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::parse('2027-07-20 10:00'));
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->from = AcademicSession::where('is_current', true)->first();
        $this->to = AcademicSession::create(['name' => '2027-28', 'starts_on' => '2027-08-01', 'ends_on' => '2028-07-31']);
        $this->actingAs($this->admin, 'sanctum');
    }

    /**
     * @return array<string, int>
     */
    private function sessions(): array
    {
        return ['from_session_id' => $this->from->id, 'to_session_id' => $this->to->id];
    }

    private function publishFinalResult(Enrollment $enrollment, string $result): void
    {
        $term = ExamTerm::firstOrCreate(['academic_session_id' => $this->from->id, 'name' => 'Annual'], ['is_final' => true]);
        $exam = Exam::firstOrCreate(['exam_term_id' => $term->id, 'class_id' => $enrollment->class_id, 'name' => 'Annual Exam'], ['status' => 'published', 'published_at' => now()]);

        ReportCard::create(['exam_id' => $exam->id, 'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'section_id' => $enrollment->section_id, 'total_max' => 500, 'total_obtained' => 157.5, 'percentage' => 31.5, 'result' => $result]);
    }

    private function enrol(SchoolClass $class): Enrollment
    {
        return Enrollment::create([
            'student_id' => Student::factory()->create()->id,
            'academic_session_id' => $this->from->id,
            'class_id' => $class->id,
            'enrolled_on' => $this->from->starts_on,
            'status' => 'active',
        ]);
    }

    public function test_prepare_copies_sections_once_and_reports_counts(): void
    {
        $fromSections = Section::where('academic_session_id', $this->from->id)->count();

        $this->postJson('/api/erp/promotions/prepare', $this->sessions())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sections', $fromSections);

        $this->postJson('/api/erp/promotions/prepare', $this->sessions())->assertOk()->assertJsonPath('data.sections', 0);
        $this->assertSame($fromSections, Section::where('academic_session_id', $this->to->id)->count());

        $this->postJson('/api/erp/promotions/prepare', ['from_session_id' => $this->to->id, 'to_session_id' => $this->from->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'to_session_id');
    }

    public function test_class_is_promoted_or_kept_back_by_its_final_result(): void
    {
        $grade5 = SchoolClass::where('name', 'Grade 5')->first();
        $enrollments = Enrollment::where('academic_session_id', $this->from->id)->where('class_id', $grade5->id)->where('status', 'active')->orderBy('id')->get();
        $failed = $enrollments->first();
        $this->publishFinalResult($failed, 'fail');

        $this->postJson('/api/erp/promotions/prepare', $this->sessions())->assertOk();
        $grade6Section = Section::where('academic_session_id', $this->to->id)->where('class_id', $grade5->next_class_id)->first();

        $this->getJson('/api/erp/promotions?'.http_build_query($this->sessions()))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Grade 5', 'waiting' => $enrollments->count(), 'moved' => 0]);

        $students = $this->getJson('/api/erp/promotions/students?'.http_build_query([...$this->sessions(), 'class_id' => $grade5->id]))
            ->assertOk()
            ->assertJsonPath('data.next_class.name', 'Grade 6')
            ->json('data.students');
        $this->assertSame(['repeat', ...array_fill(0, $enrollments->count() - 1, 'promote')], array_column($students, 'suggested'));
        $this->assertSame('fail', $students[0]['result']);

        $moves = collect($students)->map(fn (array $row): array => [
            'enrollment_id' => $row['enrollment_id'],
            'action' => $row['suggested'],
            'section_id' => $row['suggested'] === 'promote' ? $grade6Section->id : null,
        ])->all();

        $this->postJson('/api/erp/promotions', [...$this->sessions(), 'class_id' => $grade5->id, 'students' => $moves])
            ->assertOk()
            ->assertJsonPath('data.promoted', $enrollments->count() - 1)
            ->assertJsonPath('data.repeated', 1);

        $kept = $failed->fresh();
        $this->assertSame('repeated', $kept->status);
        $this->assertSame('fail', $kept->result_status);
        $this->assertSame($grade5->id, Enrollment::find($kept->promoted_to_enrollment_id)->class_id);

        $promoted = $enrollments->last()->fresh();
        $this->assertSame('promoted', $promoted->status);
        $this->assertDatabaseHas('enrollments', ['id' => $promoted->promoted_to_enrollment_id, 'academic_session_id' => $this->to->id, 'class_id' => $grade5->next_class_id, 'section_id' => $grade6Section->id, 'status' => 'active']);
        $this->assertTrue(ActivityLog::where('module', 'promotions')->where('action', 'promote')->exists());

        $this->getJson('/api/erp/promotions?'.http_build_query($this->sessions()))
            ->assertJsonFragment(['name' => 'Grade 5', 'waiting' => 0, 'moved' => $enrollments->count()]);

        $this->postJson('/api/erp/promotions', [...$this->sessions(), 'class_id' => $grade5->id, 'students' => $moves])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'students');
    }

    public function test_last_class_graduates_instead_of_promoting(): void
    {
        $grade10 = SchoolClass::where('name', 'Grade 10')->first();
        $enrollment = $this->enrol($grade10);
        $move = fn (string $action): array => [...$this->sessions(), 'class_id' => $grade10->id, 'students' => [['enrollment_id' => $enrollment->id, 'action' => $action]]];

        $this->getJson('/api/erp/promotions/students?'.http_build_query([...$this->sessions(), 'class_id' => $grade10->id]))
            ->assertJsonPath('data.students.0.suggested', 'graduate');

        $this->postJson('/api/erp/promotions', $move('promote'))->assertUnprocessable();

        $this->postJson('/api/erp/promotions', $move('graduate'))->assertOk()->assertJsonPath('data.graduated', 1);

        $this->assertSame('graduated', $enrollment->fresh()->status);
        $this->assertSame('graduated', $enrollment->student->fresh()->current_status);
        $this->assertSame('2027-07-31', $enrollment->student->fresh()->left_on->toDateString());
        $this->assertFalse(Enrollment::where('academic_session_id', $this->to->id)->where('student_id', $enrollment->student_id)->exists());
    }

    public function test_student_can_be_moved_down_to_the_class_before(): void
    {
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $prep = SchoolClass::where('next_class_id', $grade1->id)->first();
        $enrollment = $this->enrol($grade1);

        $this->getJson('/api/erp/promotions/students?'.http_build_query([...$this->sessions(), 'class_id' => $grade1->id]))
            ->assertJsonPath('data.previous_class.name', $prep->name);

        $this->postJson('/api/erp/promotions', [...$this->sessions(), 'class_id' => $grade1->id, 'students' => [['enrollment_id' => $enrollment->id, 'action' => 'demote']]])
            ->assertOk()
            ->assertJsonPath('data.demoted', 1);

        $this->assertSame('demoted', $enrollment->fresh()->status);
        $this->assertSame($prep->id, Enrollment::find($enrollment->fresh()->promoted_to_enrollment_id)->class_id);

        $playgroup = SchoolClass::where('level_order', SchoolClass::min('level_order'))->first();
        $first = $this->enrol($playgroup);

        $this->postJson('/api/erp/promotions', [...$this->sessions(), 'class_id' => $playgroup->id, 'students' => [['enrollment_id' => $first->id, 'action' => 'demote']]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'students');
    }

    public function test_student_history_shows_each_session_with_result_attendance_and_fees(): void
    {
        $grade5 = SchoolClass::where('name', 'Grade 5')->first();
        $section = Section::where('academic_session_id', $this->from->id)->where('class_id', $grade5->id)->first();
        $enrollment = $this->enrol($grade5);
        $enrollment->update(['section_id' => $section->id]);
        $this->publishFinalResult($enrollment, 'fail');

        foreach (['2027-07-01' => 'present', '2027-07-02' => 'absent'] as $date => $status) {
            StudentAttendance::create(['enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'section_id' => $section->id, 'date' => $date, 'status' => $status]);
        }

        $this->postJson('/api/erp/promotions', [...$this->sessions(), 'class_id' => $grade5->id, 'students' => [['enrollment_id' => $enrollment->id, 'action' => 'repeat']]])->assertOk();

        $history = $this->getJson("/api/erp/students/{$enrollment->student_id}/history")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame(['2027-28', '2026-27'], array_column(array_column($history, 'session'), 'name'));
        $this->assertSame(['active', 'repeated'], array_column($history, 'status'));
        $this->assertSame('Grade 5', $history[0]['class']);
        $this->assertSame('fail', $history[1]['result_status']);
        $this->assertSame('Annual Exam', $history[1]['results'][0]['exam']);
        $this->assertEquals(50, $history[1]['attendance']['percentage']);
        $this->assertSame(['challans' => 0, 'billed' => 0, 'paid' => 0, 'waived' => 0, 'balance' => 0], $history[0]['fees']);

        $teacher = User::where('email', 'teacher@lms.test')->first();
        $this->actingAs($teacher, 'sanctum')->getJson("/api/erp/students/{$enrollment->student_id}/history")->assertNotFound();
    }

    public function test_concessions_and_transport_follow_the_student_and_undo_puts_everything_back(): void
    {
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $enrollment = $this->enrol($grade1);
        $studentId = $enrollment->student_id;
        StudentConcession::create(['student_id' => $studentId, 'concession_type_id' => ConcessionType::where('name', 'Sibling')->value('id'), 'calculation' => 'percent', 'value' => 1000, 'valid_from' => '2026-08-01', 'valid_to' => '2027-07-31', 'status' => 'approved']);
        $stop = TransportStop::first();
        $ride = StudentTransport::create(['student_id' => $studentId, 'transport_route_id' => $stop->transport_route_id, 'transport_stop_id' => $stop->id, 'academic_session_id' => $this->from->id, 'from_date' => '2026-08-01', 'status' => 'active']);
        $move = [...$this->sessions(), 'class_id' => $grade1->id, 'students' => [['enrollment_id' => $enrollment->id, 'action' => 'promote']]];

        $this->postJson('/api/erp/promotions', $move)
            ->assertOk()
            ->assertJsonPath('data.concessions', 1)
            ->assertJsonPath('data.transport', 1);

        $this->assertTrue(StudentConcession::where('student_id', $studentId)->whereDate('valid_from', '2027-08-01')->whereDate('valid_to', '2028-07-31')->where('status', 'approved')->exists());
        $this->assertSame('ended', $ride->fresh()->status);
        $this->assertTrue(StudentTransport::where('student_id', $studentId)->where('academic_session_id', $this->to->id)->where('status', 'active')->exists());

        $this->postJson('/api/erp/promotions/undo', [...$this->sessions(), 'class_id' => $grade1->id])->assertOk()->assertJsonPath('data.students', 1);

        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertNull($enrollment->fresh()->promoted_to_enrollment_id);
        $this->assertFalse(Enrollment::where('academic_session_id', $this->to->id)->where('student_id', $studentId)->exists());
        $this->assertSame(['active', null], [$ride->fresh()->status, $ride->fresh()->to_date]);
        $this->assertFalse(StudentTransport::where('student_id', $studentId)->where('academic_session_id', $this->to->id)->exists());
        $this->assertFalse(StudentConcession::where('student_id', $studentId)->whereDate('valid_from', '2027-08-01')->exists());

        // Moving again brings back the same place instead of clashing with it.
        $this->postJson('/api/erp/promotions', $move)->assertOk()->assertJsonPath('data.promoted', 1);
        $this->assertSame(1, Enrollment::withTrashed()->where('academic_session_id', $this->to->id)->where('student_id', $studentId)->count());
    }

    public function test_undo_is_refused_once_the_new_session_has_records_and_brings_graduates_back(): void
    {
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $enrollment = $this->enrol($grade1);
        $this->postJson('/api/erp/promotions', [...$this->sessions(), 'class_id' => $grade1->id, 'students' => [['enrollment_id' => $enrollment->id, 'action' => 'promote']]])->assertOk();
        $new = Enrollment::find($enrollment->fresh()->promoted_to_enrollment_id);
        $section = Section::where('academic_session_id', $this->from->id)->first();
        StudentAttendance::create(['enrollment_id' => $new->id, 'student_id' => $new->student_id, 'section_id' => $section->id, 'date' => '2027-08-02', 'status' => 'present']);

        $this->postJson('/api/erp/promotions/undo', [...$this->sessions(), 'class_id' => $grade1->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'class_id');
        $this->assertSame('promoted', $enrollment->fresh()->status);

        $grade10 = SchoolClass::where('name', 'Grade 10')->first();
        $graduate = $this->enrol($grade10);
        $this->postJson('/api/erp/promotions', [...$this->sessions(), 'class_id' => $grade10->id, 'students' => [['enrollment_id' => $graduate->id, 'action' => 'graduate']]])->assertOk();

        $this->postJson('/api/erp/promotions/undo', [...$this->sessions(), 'class_id' => $grade10->id])->assertOk();
        $this->assertSame('active', $graduate->student->fresh()->current_status);
        $this->assertSame('active', $graduate->fresh()->status);
    }

    public function test_roll_numbers_are_given_per_section_in_name_order(): void
    {
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $section = Section::create(['academic_session_id' => $this->to->id, 'class_id' => $grade1->id, 'name' => 'A']);
        foreach (['Zara', 'Ahmed', 'Mona'] as $name) {
            $student = Student::factory()->create(['first_name' => $name]);
            Enrollment::create(['student_id' => $student->id, 'academic_session_id' => $this->to->id, 'class_id' => $grade1->id, 'section_id' => $section->id, 'enrolled_on' => '2027-08-01', 'status' => 'active']);
        }

        $this->postJson('/api/erp/promotions/roll-numbers', ['to_session_id' => $this->to->id, 'class_id' => $grade1->id, 'order' => 'name'])
            ->assertOk()
            ->assertJsonPath('data.students', 3);

        $this->assertSame(['Ahmed' => '1', 'Mona' => '2', 'Zara' => '3'], Enrollment::with('student')->where('section_id', $section->id)->get()->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->student->first_name => $enrollment->roll_no])->sortKeys()->all());

        $this->postJson('/api/erp/promotions/roll-numbers', ['to_session_id' => $this->to->id, 'class_id' => $grade1->id])->assertUnprocessable()->assertJsonPath('errors.first_key', 'order');
    }

    public function test_section_must_belong_to_the_new_class_and_session(): void
    {
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $enrollment = $this->enrol($grade1);
        $wrongSection = Section::where('academic_session_id', $this->from->id)->where('class_id', $grade1->next_class_id)->first()
            ?? Section::create(['academic_session_id' => $this->from->id, 'class_id' => $grade1->next_class_id, 'name' => 'A']);

        $this->postJson('/api/erp/promotions', [...$this->sessions(), 'class_id' => $grade1->id, 'students' => [['enrollment_id' => $enrollment->id, 'action' => 'promote', 'section_id' => $wrongSection->id]]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'students');

        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_promotion_needs_student_management_permission(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($this->admin)->get('/admin/students/promotion')->assertOk()->assertSee('Year-end Promotion');
        $this->actingAs($teacher)->get('/admin/students/promotion')->assertForbidden();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/promotions?'.http_build_query($this->sessions()))->assertForbidden()->assertJsonPath('success', false);
        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/promotions', [])->assertForbidden();
    }
}
