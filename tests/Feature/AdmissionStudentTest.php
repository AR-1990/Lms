<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\AdmissionInquiry;
use App\Models\CardTemplate;
use App\Models\FeeInvoice;
use App\Models\Guardian;
use App\Models\IdCard;
use App\Models\Lookup;
use App\Models\PaymentMethod;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdmissionStudentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicSession $session;

    private SchoolClass $grade3;

    /**
     * Seeded academics, fee structures and demo students.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->session = AcademicSession::where('is_current', true)->first();
        $this->grade3 = SchoolClass::where('name', 'Grade 3')->first();
    }

    private function lookup(string $type, ?string $name = null): int
    {
        return Lookup::where('type', $type)->when($name, fn ($query) => $query->where('name', $name))->orderBy('sort_order')->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(array $overrides = []): array
    {
        return [
            'academic_session_id' => $this->session->id,
            'class_applied_id' => $this->grade3->id,
            'first_name' => 'Zainab',
            'last_name' => 'Ahmed',
            'gender_id' => $this->lookup('gender', 'Female'),
            'date_of_birth' => '2018-04-12',
            'b_form_no' => '35202-7654321-2',
            'guardians' => [
                ['relation_id' => $this->lookup('guardian_relation', 'Father'), 'name' => 'Imran Ahmed', 'phone' => '03334445556', 'cnic' => '35202-2222222-2'],
                ['relation_id' => $this->lookup('guardian_relation', 'Mother'), 'name' => 'Sana Imran', 'phone' => '03334445557'],
            ],
            ...$overrides,
        ];
    }

    private function moveTo(int $applicationId, string $status): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/admission-applications/{$applicationId}/status", ['status' => $status])
            ->assertOk();
    }

    private function payApplicant(int $applicationId, int $amount): TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/payments', [
            'admission_application_id' => $applicationId,
            'payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id'),
            'amount' => $amount,
            'received_on' => '2026-10-22',
        ]);
    }

    public function test_application_raises_the_registration_challan_and_records_guardians(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/admission-applications', $this->applicationPayload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.class_name', 'Grade 3')
            ->assertJsonCount(2, 'data.guardians')
            ->assertJsonPath('data.guardians.0.is_primary_contact', true)
            ->assertJsonPath('data.invoices.0.invoice_type', 'registration')
            ->assertJsonPath('data.invoices.0.total', 100000);

        $invoice = FeeInvoice::where('admission_application_id', $response->json('data.id'))->first();
        $this->assertNull($invoice->student_id);
        $this->assertNotNull($invoice->journal_entry_id);
    }

    public function test_application_validation(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/admission-applications', $this->applicationPayload(['b_form_no' => '123', 'guardians' => [['relation_id' => $this->lookup('guardian_relation'), 'name' => 'No Phone']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['b_form_no', 'guardians.0.phone'], 'errors.fields');
    }

    public function test_full_admission_flow_to_enrolment(): void
    {
        $id = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/admission-applications', $this->applicationPayload())->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/admission-applications/{$id}/status", ['status' => 'accepted'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'status');

        $test = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/admission-applications/{$id}/tests", ['scheduled_at' => '2026-10-25 09:00', 'subject_id' => Subject::where('code', 'ENG')->value('id'), 'max_marks' => 50])
            ->assertCreated()
            ->assertJsonPath('data.status', 'test_scheduled')
            ->json('data.tests.0.id');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/erp/admission-tests/{$test}", ['scheduled_at' => '2026-10-25 09:00', 'obtained_marks' => 60])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.obtained_marks.0', 'Marks cannot be more than 50.');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/erp/admission-tests/{$test}", ['scheduled_at' => '2026-10-25 09:00', 'obtained_marks' => 41, 'result' => 'pass'])
            ->assertOk()
            ->assertJsonPath('data.status', 'test_done');

        $interview = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/admission-applications/{$id}/interviews", ['scheduled_at' => '2026-10-26 11:00'])
            ->assertJsonPath('data.status', 'interview_scheduled')
            ->json('data.interviews.0.id');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/erp/admission-interviews/{$interview}", ['scheduled_at' => '2026-10-26 11:00', 'attended' => true, 'overall_rating' => 4, 'recommendation' => 'admit'])
            ->assertJsonPath('data.status', 'interviewed');

        $this->moveTo($id, 'offered');
        $this->moveTo($id, 'accepted');

        $admissionInvoice = FeeInvoice::where('admission_application_id', $id)->where('invoice_type', 'admission')->first();
        $this->assertSame(2600000, $admissionInvoice->total);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/admission-applications/{$id}/enrol", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.fees.0', 'The admission fee of Rs 26,000.00 is unpaid. Receive it or waive it first.');

        $this->payApplicant($id, 2800000)->assertUnprocessable()->assertJsonPath('errors.first_key', 'amount');
        $this->payApplicant($id, 2700000)->assertCreated()->assertJsonCount(2, 'data.allocations');

        $wrongSection = Section::where('academic_session_id', $this->session->id)->whereHas('schoolClass', fn ($query) => $query->where('name', 'Grade 4'))->first();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/admission-applications/{$id}/enrol", ['section_id' => $wrongSection->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.first_key', 'section_id');

        $section = Section::where('class_id', $this->grade3->id)->where('academic_session_id', $this->session->id)->where('name', 'B')->first();
        $enrolled = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/admission-applications/{$id}/enrol", ['section_id' => $section->id, 'roll_no' => '17', 'parent_password' => 'secret-pass', 'student_password' => 'student-pass'])
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Zainab Ahmed')
            ->assertJsonPath('data.enrollments.0.section', 'B')
            ->assertJsonPath('data.enrollments.0.roll_no', '17')
            ->assertJsonCount(2, 'data.guardians');

        $student = Student::find($enrolled->json('data.id'));
        $this->assertMatchesRegularExpression('/^PIA-2026-\d{5}$/', $student->admission_no);
        $this->assertSame('enrolled', AdmissionApplication::find($id)->status);
        // Registration and admission challans, plus this month's tuition challan made at enrolment.
        $this->assertSame(3, FeeInvoice::where('student_id', $student->id)->count());
        $this->assertSame(1, FeeInvoice::where('student_id', $student->id)->where('invoice_type', 'monthly')->whereDate('billing_month', '2026-10-01')->count());
        $this->assertSame(CardTemplate::where('card_type', 'student')->where('is_active', true)->exists() ? 1 : 0, IdCard::where('holder_type', 'student')->where('holder_id', $student->id)->count());
        $this->postJson('/api/login', ['email' => $student->admission_no, 'password' => 'student-pass'])->assertOk()->assertJsonPath('data.user.name', 'Zainab Ahmed');

        $this->postJson('/api/login', ['email' => '03334445556', 'password' => 'secret-pass'])->assertOk()->assertJsonPath('data.user.name', 'Imran Ahmed');
    }

    public function test_inquiry_converts_into_an_application(): void
    {
        $inquiry = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/admission-inquiries', [
            'parent_name' => 'Imran Ahmed',
            'phone' => '03334445556',
            'child_name' => 'Zainab',
            'class_sought_id' => $this->grade3->id,
        ])->assertCreated()->assertJsonPath('data.status', 'new')->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/admission-inquiries/{$inquiry}/convert", $this->applicationPayload())
            ->assertCreated();

        $this->assertSame('converted', AdmissionInquiry::find($inquiry)->status);
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/erp/admission-inquiries/{$inquiry}")->assertUnprocessable();
    }

    public function test_direct_student_entry_reuses_an_existing_guardian_by_cnic(): void
    {
        $parent = Guardian::where('cnic', '35202-1111111-1')->first();
        $grade1 = SchoolClass::where('name', 'Grade 1')->first();
        $section = Section::where('class_id', $grade1->id)->where('academic_session_id', $this->session->id)->first();

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/students', [
            'admission_date' => '2026-08-01',
            'first_name' => 'Ali',
            'last_name' => 'Malik',
            'gender_id' => $this->lookup('gender', 'Male'),
            'date_of_birth' => '2020-01-01',
            'enrollment' => ['academic_session_id' => $this->session->id, 'class_id' => $grade1->id, 'section_id' => $section->id],
            'guardians' => [['relation_id' => $this->lookup('guardian_relation', 'Father'), 'name' => 'Farhan Malik', 'phone' => '03001234567', 'cnic' => '35202-1111111-1']],
        ])->assertCreated();

        $this->assertSame($parent->id, $response->json('data.guardians.0.id'));
        $this->assertSame(2, $parent->students()->count());

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/students', [
            'admission_date' => '2026-08-01',
            'first_name' => 'Wrong',
            'gender_id' => $this->lookup('gender'),
            'date_of_birth' => '2020-01-01',
            'enrollment' => ['academic_session_id' => $this->session->id, 'class_id' => $this->grade3->id, 'section_id' => $section->id],
            'guardians' => [['relation_id' => $this->lookup('guardian_relation'), 'name' => 'X', 'phone' => '03001112223']],
        ])->assertUnprocessable()->assertJsonPath('errors.first_key', 'enrollment.section_id');
    }

    public function test_withdrawal_closes_enrolment_and_login_and_fee_records_block_deletion(): void
    {
        $john = Student::where('first_name', 'John')->first();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/invoices/generate', ['academic_session_id' => $this->session->id, 'billing_month' => '2026-10']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/students/{$john->id}/status", ['status' => 'withdrawn', 'effective_on' => '2026-10-31', 'reason_id' => $this->lookup('withdrawal_reason')])
            ->assertOk()
            ->assertJsonPath('data.current_status', 'withdrawn')
            ->assertJsonPath('data.enrollments.0.status', 'withdrawn');

        $this->assertFalse($john->user->refresh()->is_active);
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/erp/students/{$john->id}")->assertUnprocessable()->assertJsonPath('errors.first_key', 'student');

        $guardian = $john->guardians()->first();
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/erp/students/{$john->id}/guardians/{$guardian->id}")->assertUnprocessable();
    }

    public function test_student_list_filters_by_class_and_permission(): void
    {
        $grade5 = SchoolClass::where('name', 'Grade 5')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/students?class_id='.$grade5->id)
            ->assertOk()
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonPath('data.items.0.class_name', 'Grade 5');

        $teacher = User::where('email', 'teacher@lms.test')->first();
        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/students')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/admission-applications', [])->assertForbidden();
    }

    public function test_student_and_admission_screens_render_for_admin_and_are_forbidden_to_teachers(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $john = Student::where('first_name', 'John')->first();

        foreach (['admin.students' => 'students', 'admin.admissions.applications' => 'applications', 'admin.admissions.inquiries' => 'inquiries'] as $route => $screen) {
            $this->actingAs($this->admin)
                ->get(route($route))
                ->assertOk()
                ->assertSee('data-students="'.$screen.'"', false)
                ->assertSee('js/admin-students.js', false);

            $this->actingAs($teacher)->get(route($route))->assertForbidden();
        }

        $this->actingAs($this->admin)
            ->get(route('admin.students.show', $john))
            ->assertOk()
            ->assertSee('data-student-profile', false)
            ->assertSee('js/resource-screen.js', false)
            ->assertSee('Farhan Malik');
        $this->actingAs($teacher)->get(route('admin.students.show', $john))->assertForbidden();
    }

    public function test_unlinking_a_guardian_keeps_the_link_as_history_and_allows_relinking(): void
    {
        $john = Student::where('first_name', 'John')->first();
        $mother = ['relation_id' => $this->lookup('guardian_relation', 'Mother'), 'name' => 'Ayesha Malik', 'phone' => '03009998887'];

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/students/{$john->id}/guardians", $mother)->assertCreated();
        $guardian = Guardian::where('phone', '03009998887')->first();

        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/erp/students/{$john->id}/guardians/{$guardian->id}")->assertOk();
        $this->assertFalse($john->guardians()->whereKey($guardian->id)->exists());
        $this->assertDatabaseHas('guardian_student', ['student_id' => $john->id, 'guardian_id' => $guardian->id]);
        $this->assertNotNull(DB::table('guardian_student')->where('guardian_id', $guardian->id)->value('deleted_at'));

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/students/{$john->id}/guardians", ['guardian_id' => $guardian->id, 'relation_id' => $mother['relation_id']])->assertCreated();
        $this->assertTrue($john->guardians()->whereKey($guardian->id)->exists());
    }
}
