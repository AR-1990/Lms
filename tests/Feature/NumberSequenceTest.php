<?php

namespace Tests\Feature;

use App\Models\NumberSequence;
use App\Models\User;
use App\Services\NumberSequenceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /**
     * Set up seeded users and the number formats.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(NumberSequence $sequence, array $overrides = []): array
    {
        return [
            'label' => $sequence->label,
            'prefix' => $sequence->prefix,
            'include_year' => $sequence->include_year,
            'padding' => $sequence->padding,
            'next_number' => $sequence->next_number,
            'reset_yearly' => $sequence->reset_yearly,
            ...$overrides,
        ];
    }

    public function test_next_hands_out_consecutive_formatted_numbers(): void
    {
        $service = app(NumberSequenceService::class);

        $this->assertSame('PIA-2026-00001', $service->next('admission_no'));
        $this->assertSame('PIA-2026-00002', $service->next('admission_no'));
        $this->assertSame('EMP-0001', $service->next('employee_code'));
        $this->assertSame(3, NumberSequence::where('type', 'admission_no')->value('next_number'));
    }

    public function test_yearly_sequences_restart_in_a_new_year_and_others_keep_counting(): void
    {
        $service = app(NumberSequenceService::class);
        $service->next('fee_invoice');
        $service->next('admission_no');

        $this->travelTo(now()->setDate(2027, 1, 2));

        $this->assertSame('INV-2027-000001', $service->next('fee_invoice'));
        $this->assertSame('PIA-2027-00002', $service->next('admission_no'));
    }

    public function test_unknown_type_fails_loudly(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(NumberSequenceService::class)->next('does_not_exist');
    }

    public function test_admin_can_change_format_and_skip_ahead_with_preview(): void
    {
        $sequence = NumberSequence::where('type', 'admission_no')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/number-sequences/'.$sequence->id, $this->payload($sequence, ['prefix' => 'ADM/', 'include_year' => false, 'padding' => 4, 'next_number' => 120]))
            ->assertOk()
            ->assertJsonPath('data.preview', 'ADM/0120');

        $this->assertSame('ADM/0120', app(NumberSequenceService::class)->next('admission_no'));
        $this->assertDatabaseHas('activity_logs', ['module' => 'numbering', 'action' => 'updated', 'subject_id' => $sequence->id]);
    }

    public function test_counter_cannot_move_backwards_or_take_unsafe_prefix(): void
    {
        $sequence = NumberSequence::where('type', 'receipt')->first();
        $sequence->update(['next_number' => 50]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/erp/number-sequences/'.$sequence->id, $this->payload($sequence, ['next_number' => 10, 'prefix' => '<b>']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['next_number', 'prefix'], 'errors.fields');

        $this->assertSame(50, $sequence->refresh()->next_number);
    }

    public function test_numbering_requires_permission(): void
    {
        $teacher = User::where('email', 'teacher@lms.test')->first();
        $sequence = NumberSequence::first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/number-sequences')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->putJson('/api/erp/number-sequences/'.$sequence->id, $this->payload($sequence))->assertForbidden();
    }
}
