<?php

namespace Tests\Feature;

use App\Models\AdmissionInquiry;
use App\Models\Lookup;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AdmissionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InquiryFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_ups_are_logged_reminded_when_due_and_reported_by_source(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 10:00'));
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@lms.test')->first();
        $inquiry = AdmissionInquiry::create([
            'inquiry_no' => 'INQ-T1', 'inquiry_date' => '2026-10-20', 'parent_name' => 'Sana Iqbal', 'phone' => '03001112223', 'child_name' => 'Ayan',
            'source_id' => Lookup::where('type', 'inquiry_source')->where('name', 'Referral')->value('id'), 'status' => 'new',
        ]);

        $this->actingAs($admin, 'sanctum')->postJson("/api/erp/admission-inquiries/{$inquiry->id}/follow-ups", [
            'followed_up_on' => '2026-10-22', 'channel_id' => Lookup::where('type', 'follow_up_channel')->where('name', 'Phone call')->value('id'),
            'outcome' => 'Asked about transport; will visit on Saturday.', 'next_follow_up_on' => '2026-10-24',
        ])->assertCreated()->assertJsonPath('data.channel.name', 'Phone call');
        $this->assertSame(['contacted', '2026-10-24'], [$inquiry->refresh()->status, $inquiry->follow_up_on->toDateString()]);
        $this->actingAs($admin, 'sanctum')->getJson("/api/erp/admission-inquiries/{$inquiry->id}/follow-ups")->assertOk()->assertJsonPath('data.0.next_follow_up_on', '2026-10-24');
        $this->actingAs($admin, 'sanctum')->getJson('/api/erp/admission-inquiries/datatable?draw=1')->assertOk()->assertJsonPath('data.data.0.follow_ups_count', 1);

        $this->assertSame(0, app(AdmissionService::class)->followUpReminders());
        $this->travelTo(Carbon::parse('2026-10-25 08:45'));
        $this->assertSame(1, app(AdmissionService::class)->followUpReminders());
        $this->assertTrue(UserNotification::where('user_id', $admin->id)->where('event_key', 'inquiry_follow_ups_due')->where('body', 'like', '%Sana Iqbal (Ayan)%1 overdue%')->exists());

        $report = $this->actingAs($admin, 'sanctum')->getJson('/api/erp/reports/admission-sources?from=2026-10-01&to=2026-10-31')->assertOk()->json('data');
        $this->assertSame(1, collect($report['rows'])->firstWhere('source', 'Referral')['inquiries'] ?? null);

        $inquiry->update(['status' => 'closed']);
        $this->actingAs($admin, 'sanctum')->postJson("/api/erp/admission-inquiries/{$inquiry->id}/follow-ups", ['followed_up_on' => '2026-10-25', 'outcome' => 'Called again'])->assertUnprocessable();
        $this->actingAs(User::where('email', 'parent@lms.test')->first(), 'sanctum')->getJson("/api/erp/admission-inquiries/{$inquiry->id}/follow-ups")->assertForbidden();
    }
}
