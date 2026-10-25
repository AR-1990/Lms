<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\EmployeeLoan;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmployeeExitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = Employee::whereHas('user', fn ($query) => $query->where('email', 'teacher@lms.test'))->firstOrFail();
        $this->actingAs($this->admin, 'sanctum');
    }

    private function startExit(): int
    {
        return $this->postJson('/api/erp/employee-exits', ['employee_id' => $this->teacher->id, 'exit_type' => 'resignation', 'notice_date' => '2026-09-20', 'last_working_day' => '2026-10-20'])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_final_settlement_after_clearance_pays_one_voucher_and_closes_the_employee(): void
    {
        EmployeeLoan::create(['employee_id' => $this->teacher->id, 'amount' => 1000000, 'instalments' => 10, 'instalment_amount' => 100000, 'start_month' => '2026-05-01', 'outstanding' => 500000, 'status' => 'active']);
        $asset = Asset::factory()->create(['status' => 'assigned', 'current_holder_type' => 'employee', 'current_holder_id' => $this->teacher->id]);
        $exitId = $this->startExit();

        $this->postJson('/api/erp/employee-exits', ['employee_id' => $this->teacher->id, 'exit_type' => 'resignation', 'last_working_day' => '2026-10-20'])->assertUnprocessable();

        $show = $this->getJson("/api/erp/employee-exits/{$exitId}")->assertOk();
        $clearances = $show->json('data.exit.clearances');
        $this->assertNotEmpty($clearances);
        $salary = (int) round(9600000 * 20 / 31);
        $leave = (int) round(6000000 / 30 * 14);
        $this->assertSame([['salary', $salary], ['leave', $leave], ['loan', 500000]], array_map(fn (array $line): array => [$line['source'], $line['amount']], $show->json('data.settlement.lines')));
        $this->assertCount(2, $show->json('data.settlement.blocking'));

        $pay = ['payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id'), 'paid_on' => '2026-10-22', 'lines' => [['label' => 'Gratuity', 'type' => 'earning', 'amount' => 100000]]];
        $this->postJson("/api/erp/employee-exits/{$exitId}/settle", $pay)->assertUnprocessable()->assertJsonPath('errors.first_key', 'settlement');

        foreach ($clearances as $clearance) {
            $this->putJson("/api/erp/exit-clearances/{$clearance['id']}", ['cleared' => true])->assertOk();
        }
        Asset::where('current_holder_type', 'employee')->where('current_holder_id', $this->teacher->id)->get()->each(fn (Asset $held) => $held->forceFill(['status' => 'in_store', 'current_holder_type' => null, 'current_holder_id' => null])->save());

        $net = $salary + $leave + 100000 - 500000;
        $this->postJson("/api/erp/employee-exits/{$exitId}/preview", ['lines' => $pay['lines']])->assertOk()->assertJsonPath('data.net', $net)->assertJsonPath('data.blocking', []);

        $this->postJson("/api/erp/employee-exits/{$exitId}/settle", $pay)->assertOk()->assertJsonPath('data.status', 'settled')->assertJsonPath('data.final_settlement_amount', $net);

        $exit = EmployeeExit::with('lines')->findOrFail($exitId);
        $this->assertCount(4, $exit->lines);
        $entry = JournalEntry::with('lines')->findOrFail($exit->journal_entry_id);
        $this->assertEquals($salary + $leave + 100000, $entry->lines->firstWhere('account_id', Account::where('code', '5100')->value('id'))->debit);
        $this->assertEquals(500000, $entry->lines->firstWhere('account_id', Account::where('code', '1400')->value('id'))->credit);
        $this->assertSame('resigned', $this->teacher->fresh()->status);
        $this->assertFalse($this->teacher->user->fresh()->is_active);
        $this->assertSame(0, EmployeeLoan::where('employee_id', $this->teacher->id)->sum('outstanding'));

        $this->get("/api/erp/employee-exits/{$exitId}/experience-letter")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->postJson("/api/erp/employee-exits/{$exitId}/settle", $pay)->assertUnprocessable();
    }

    public function test_exit_can_be_cancelled_and_is_hr_only(): void
    {
        $exitId = $this->startExit();

        $this->get("/api/erp/employee-exits/{$exitId}/experience-letter")->assertUnprocessable();
        $this->postJson("/api/erp/employee-exits/{$exitId}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('active', $this->teacher->fresh()->status);
        $this->getJson('/api/erp/employee-exits/datatable?draw=1')->assertOk()->assertJsonPath('data.recordsTotal', 1);

        $this->actingAs($this->admin)->get('/admin/hr/exits')->assertOk()->assertSee('Staff Exits');
        $teacherUser = User::where('email', 'teacher@lms.test')->first();
        $this->actingAs($teacherUser, 'sanctum')->postJson('/api/erp/employee-exits', ['employee_id' => $this->teacher->id, 'exit_type' => 'resignation', 'last_working_day' => '2026-10-30'])->assertForbidden();
    }
}
