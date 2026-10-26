<?php

namespace Tests\Feature;

use App\Models\FeeHead;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentProofTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $parent;

    private Student $child;

    private PaymentMethod $jazzCash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->parent = User::where('email', 'parent@lms.test')->first();
        $this->child = Student::findOrFail(Student::ownIdsFor($this->parent)[0]);
        $this->jazzCash = PaymentMethod::where('code', 'JAZZ')->firstOrFail();
    }

    private function challan(int $amount = 500000): FeeInvoice
    {
        $id = $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/fees/invoices', [
            'student_id' => $this->child->id, 'issue_date' => '2026-10-01', 'due_date' => '2026-10-30',
            'items' => [['fee_head_id' => FeeHead::where('is_active', true)->value('id'), 'amount' => $amount]],
        ])->assertCreated()->json('data.id');

        return FeeInvoice::findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function proof(FeeInvoice $challan, string $reference = 'TX123456'): array
    {
        return [
            'fee_invoice_id' => $challan->id, 'payment_method_id' => $this->jazzCash->id, 'amount' => 500000, 'paid_on' => '2026-10-21',
            'reference_no' => $reference, 'payer_name' => '0300 1112223', 'screenshot' => UploadedFile::fake()->image('jazzcash.png', 400, 700),
        ];
    }

    public function test_a_parent_sends_a_screenshot_and_fee_staff_approve_it_into_a_receipt(): void
    {
        $challan = $this->challan();
        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/fees/students/{$this->child->id}/payment-proofs")->assertOk()->assertJsonCount(0, 'data.pay_to.methods');

        $this->jazzCash->update(['payment_instructions' => 'JazzCash 0300 1234567 — Pinnacle International Academy']);
        $this->actingAs($this->parent, 'sanctum')->getJson("/api/erp/fees/students/{$this->child->id}/payment-proofs")->assertOk()->assertJsonPath('data.pay_to.methods.0.name', 'JazzCash');

        $sent = $this->actingAs($this->parent, 'sanctum')->post("/api/erp/fees/students/{$this->child->id}/payment-proofs", $this->proof($challan), ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');
        $proof = PaymentProof::findOrFail($sent['id']);
        Storage::disk('local')->assertExists($proof->screenshot->path);
        $this->assertTrue(UserNotification::where('user_id', $this->admin->id)->where('event_key', 'payment_proof_received')->exists());

        $this->actingAs($this->parent, 'sanctum')->post("/api/erp/fees/students/{$this->child->id}/payment-proofs", $this->proof($challan, 'tx123456'), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.fields.reference_no.0', 'This transaction ID has already been sent.');

        $this->actingAs($this->parent)->get(route('erp.fees.payment-proofs.screenshot', $proof))->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/fees/payment-proofs/datatable?draw=1&status=pending')->assertOk()->assertJsonPath('data.data.0.reference_no', 'TX123456');
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/approvals/inbox')->assertOk()
            ->assertJsonPath('data.sections', fn (array $sections): bool => collect($sections)->firstWhere('key', 'payment_proofs')['count'] === 1);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/fees/payment-proofs/{$proof->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $payment = FeePayment::findOrFail($proof->refresh()->fee_payment_id);
        $this->assertSame([500000, 'TX123456', $this->jazzCash->id], [$payment->amount, $payment->reference_no, $payment->payment_method_id]);
        $this->assertSame('paid', $challan->refresh()->status);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/fees/payment-proofs/{$proof->id}/approve")->assertUnprocessable();
    }

    public function test_rejected_proofs_tell_the_family_and_strangers_cannot_send_or_see_them(): void
    {
        $challan = $this->challan();
        $this->jazzCash->update(['payment_instructions' => 'JazzCash 0300 1234567']);
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->post("/api/erp/fees/students/{$this->child->id}/payment-proofs", $this->proof($challan), ['Accept' => 'application/json'])->assertNotFound();
        $this->actingAs($this->parent, 'sanctum')->post("/api/erp/fees/students/{$this->child->id}/payment-proofs", [...$this->proof($challan), 'payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.fields.payment_method_id.0', 'Choose how you paid from the list.');

        $id = $this->actingAs($this->parent, 'sanctum')->post("/api/erp/fees/students/{$this->child->id}/payment-proofs", $this->proof($challan), ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->actingAs($teacher)->get(route('erp.fees.payment-proofs.screenshot', $id))->assertNotFound();
        $this->actingAs($this->parent, 'sanctum')->postJson("/api/erp/fees/payment-proofs/{$id}/approve")->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/fees/payment-proofs/{$id}/reject", ['reason' => 'Transaction not found in our JazzCash account.'])->assertOk();
        $this->assertTrue(UserNotification::where('user_id', $this->parent->id)->where('event_key', 'payment_proof_rejected')->exists());
        $this->assertSame(500000, $challan->refresh()->balance);

        // A rejected transaction ID may be sent again (e.g. with the right screenshot).
        $this->actingAs($this->parent, 'sanctum')->post("/api/erp/fees/students/{$this->child->id}/payment-proofs", $this->proof($challan), ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($this->admin)->get(route('accounts.fees.online-payments'))->assertOk()->assertSee('Online Payments');
    }
}
