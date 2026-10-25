<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamTerm;
use App\Models\FeeInvoice;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\FeeAutomationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FamilyPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $parent;

    private Student $john;

    private Student $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->parent = User::where('email', 'parent@lms.test')->first();
        $this->john = Student::where('first_name', 'John')->first();
        $this->other = Student::whereKeyNot($this->john->id)->whereNotIn('id', Student::ownIdsFor($this->parent))->first();
    }

    private function generateOctober(): FeeInvoice
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/fees/invoices/generate', ['academic_session_id' => AcademicSession::where('is_current', true)->value('id'), 'billing_month' => '2026-10'])
            ->assertCreated();

        return FeeInvoice::where('student_id', $this->john->id)->where('invoice_type', 'monthly')->firstOrFail();
    }

    private function notificationsOf(User $user, string $event): int
    {
        return UserNotification::where('user_id', $user->id)->where('event_key', $event)->count();
    }

    public function test_family_overview_shows_the_child_and_hides_other_children(): void
    {
        $challan = $this->generateOctober();

        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/family/students')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->john->id);

        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/family/students/{$this->john->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.admission_no', $this->john->admission_no)
            ->assertJsonPath('data.fees.due', $challan->balance)
            ->assertJsonPath('data.fees.open_challans.0.invoice_no', $challan->invoice_no)
            ->assertJsonStructure(['data' => ['timetable', 'exams', 'leave_requests', 'transport', 'fees' => ['receipts', 'advance']]]);

        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/family/students/{$this->other->id}")->assertNotFound()->assertJsonPath('success', false);
        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/students/{$this->john->id}/history")->assertOk();
        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/students/{$this->other->id}/history")->assertNotFound();

        $this->actingAs($this->parent)->get('/family')->assertRedirect(route('family.child', $this->john->id));
        $this->actingAs($this->parent)->get("/family/students/{$this->john->id}")->assertOk()->assertSee('Child Overview');
        $this->actingAs($this->parent)->get("/family/students/{$this->other->id}")->assertNotFound();
    }

    public function test_challans_and_payments_notify_the_family_and_the_receipt_downloads(): void
    {
        $challan = $this->generateOctober();
        $this->assertSame(1, $this->notificationsOf($this->parent, 'challan_issued'));

        $receiptId = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/fees/payments', ['student_id' => $this->john->id, 'payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id'), 'amount' => $challan->balance, 'received_on' => '2026-10-22'])
            ->assertCreated()
            ->json('data.id');
        $this->assertSame(1, $this->notificationsOf($this->parent, 'payment_received'));

        $this->actingAs($this->admin, 'sanctum')->get("/api/erp/fees/payments/{$receiptId}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->parent, 'sanctum')->get("/api/erp/fees/payments/{$receiptId}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('activity_logs', ['module' => 'fee_payments', 'action' => 'printed']);

        $otherReceipt = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/fees/payments', ['student_id' => $this->other->id, 'payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id'), 'amount' => 10000, 'received_on' => '2026-10-22'])
            ->json('data.id');
        $this->actingAs($this->parent, 'sanctum')->get("/api/erp/fees/payments/{$otherReceipt}/pdf")->assertNotFound();
    }

    public function test_family_is_reminded_once_the_morning_after_the_due_date(): void
    {
        $challan = $this->generateOctober();
        $service = app(FeeAutomationService::class);

        $service->overdueAlert($challan->due_date->copy());
        $this->assertSame(0, $this->notificationsOf($this->parent, 'fee_overdue'));

        $service->overdueAlert($challan->due_date->copy()->addDay());
        $this->assertSame(1, $this->notificationsOf($this->parent, 'fee_overdue'));

        $service->overdueAlert($challan->due_date->copy()->addDays(2));
        $this->assertSame(1, $this->notificationsOf($this->parent, 'fee_overdue'));
    }

    public function test_one_child_exam_documents_are_only_for_that_family_and_published_results(): void
    {
        $term = ExamTerm::create(['academic_session_id' => AcademicSession::where('is_current', true)->value('id'), 'name' => 'Mid Term']);
        $exam = Exam::create(['exam_term_id' => $term->id, 'class_id' => $this->john->currentEnrollment->class_id, 'name' => 'Mid Term Exam', 'status' => 'scheduled']);

        $this->actingAs($this->parent, 'sanctum')->get("/api/erp/exams/{$exam->id}/report-cards/students/{$this->other->id}/pdf")->assertNotFound();
        $this->actingAs($this->parent, 'sanctum')->get("/api/erp/exams/{$exam->id}/report-cards/students/{$this->john->id}/pdf")->assertNotFound();

        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/family/students/{$this->john->id}")
            ->assertJsonPath('data.exams.0.name', 'Mid Term Exam')
            ->assertJsonPath('data.exams.0.report_card_url', null);
    }
}
