<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\Enrollment;
use App\Models\NotificationLog;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $parent;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->parent = User::where('email', 'parent@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function draft(array $overrides = []): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/announcements', [
                'title' => 'Sports day for Grade 5',
                'category_id' => AnnouncementCategory::where('name', 'Event')->value('id'),
                'summary' => 'Wear house colours on Friday.',
                'body' => 'Sports day is on Friday. Students wear house colours.',
                'audiences' => [['audience_type' => 'class', 'audience_id' => SchoolClass::where('name', 'Grade 5')->value('id')]],
                ...$overrides,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data');
    }

    public function test_publishing_notifies_only_the_chosen_audience_and_they_see_it_in_their_feed(): void
    {
        $announcement = $this->draft(['requires_acknowledgement' => true]);

        $this->assertSame(0, UserNotification::where('subject_id', $announcement['id'])->where('subject_type', (new Announcement)->getMorphClass())->count());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/announcements/{$announcement['id']}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $notified = UserNotification::where('subject_type', (new Announcement)->getMorphClass())->where('subject_id', $announcement['id'])->pluck('user_id');
        $this->assertEqualsCanonicalizing([$this->parent->id, User::where('email', 'student@lms.test')->value('id')], $notified->all());
        $this->assertDatabaseHas('activity_logs', ['module' => 'announcements', 'action' => 'published']);

        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/my-announcements')->assertOk()->assertJsonFragment(['title' => 'Sports day for Grade 5']);
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/my-announcements')->assertOk()->assertJsonMissing(['title' => 'Sports day for Grade 5']);
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/my-announcements/{$announcement['id']}/read")->assertNotFound();

        $this->actingAs($this->parent, 'sanctum')->postJson("/api/erp/my-announcements/{$announcement['id']}/read")->assertOk();
        $this->actingAs($this->parent, 'sanctum')->postJson("/api/erp/my-announcements/{$announcement['id']}/read", ['acknowledge' => true, 'device' => 'android'])
            ->assertOk()
            ->assertJsonPath('message', 'Thank you for confirming.');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/erp/announcements/{$announcement['id']}")
            ->assertOk()
            ->assertJsonPath('data.stats', ['recipients' => 2, 'read' => 1, 'acknowledged' => 1])
            ->assertJsonPath('data.views_count', 1);

        $row = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/announcements/datatable?draw=1&length=50')->assertOk()->json('data.data'))->firstWhere('id', $announcement['id']);
        $this->assertSame([1, 1], [$row['reads_count'], $row['acknowledged_count']]);

        $this->actingAs($this->parent)->get(route('notices'))->assertOk()->assertSee('Sports day for Grade 5')->assertSee('Confirmed');
    }

    public function test_a_scheduled_announcement_is_published_by_the_scheduler_and_archived_when_it_expires(): void
    {
        $announcement = $this->draft(['publish_at' => '2026-10-23 08:00:00', 'expires_at' => '2026-10-30 08:00:00']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/erp/announcements/{$announcement['id']}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');
        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/my-announcements')->assertJsonMissing(['title' => 'Sports day for Grade 5']);

        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(8, 5));
        $this->artisan('announcements:publish-due')->assertSuccessful();

        $this->assertSame('published', Announcement::find($announcement['id'])->status);
        $this->assertTrue(UserNotification::where('user_id', $this->parent->id)->where('title', 'Sports day for Grade 5')->exists());

        $this->travelTo(now()->setDate(2026, 10, 31));
        $this->artisan('announcements:publish-due')->assertSuccessful();
        $this->assertSame('archived', Announcement::find($announcement['id'])->status);
    }

    public function test_audiences_must_exist_and_something_must_be_chosen_before_publishing(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/announcements', ['title' => 'x', 'body' => 'y', 'audiences' => [['audience_type' => 'section', 'audience_id' => 999999]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audiences.0.audience_id'], 'errors.fields');

        $announcement = $this->draft(['audiences' => []]);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/announcements/{$announcement['id']}/publish")->assertUnprocessable();

        $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/announcements', ['title' => 'x', 'body' => 'y'])->assertForbidden();
    }

    public function test_public_announcements_show_on_the_website_and_private_ones_do_not(): void
    {
        $public = $this->draft(['title' => 'Admissions open for 2027', 'is_public' => true, 'audiences' => []]);
        $private = $this->draft(['title' => 'Grade 5 picnic money']);

        foreach ([$public, $private] as $announcement) {
            $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/announcements/{$announcement['id']}/publish")->assertOk();
        }

        $this->get(route('news'))->assertOk()->assertSee('Admissions open for 2027')->assertDontSee('Grade 5 picnic money');
        $this->get(route('home'))->assertOk()->assertSee('Admissions open for 2027');
    }

    public function test_sms_is_logged_as_not_configured_and_email_is_sent_on_the_queue(): void
    {
        Mail::fake();
        $announcement = $this->draft(['send_sms' => true, 'send_email' => true]);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/announcements/{$announcement['id']}/publish")->assertOk();

        $this->assertDatabaseHas('notification_logs', ['channel' => 'email', 'recipient' => $this->parent->email, 'status' => 'sent']);
        $this->assertSame('No sms provider is configured yet.', NotificationLog::where('channel', 'sms')->where('user_id', $this->parent->id)->value('error'));
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/notification-logs/datatable?draw=1&channel=sms')->assertOk()->assertJsonPath('data.data.0.status', 'failed');
    }

    public function test_the_bell_counts_unread_and_marks_only_own_notifications_read(): void
    {
        $unread = UserNotification::where('user_id', $this->parent->id)->whereNull('read_at')->count();
        $this->assertGreaterThan(0, $unread);

        $first = $this->actingAs($this->parent, 'sanctum')
            ->getJson('/api/erp/notifications/summary')
            ->assertOk()
            ->assertJsonPath('data.unread', $unread)
            ->json('data.latest.0.id');

        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/notifications/{$first}/read")->assertOk();
        $this->assertNull(UserNotification::find($first)->read_at);

        $this->actingAs($this->parent, 'sanctum')->postJson("/api/erp/notifications/{$first}/read")->assertOk()->assertJsonPath('data.unread', $unread - 1);
        $this->actingAs($this->parent, 'sanctum')->postJson('/api/erp/notifications/read-all')->assertOk();
        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/notifications?unread=1')->assertOk()->assertJsonPath('data.meta.total', 0);
    }

    public function test_marking_a_student_absent_sends_the_absent_alert_to_the_family(): void
    {
        $section = Section::query()->join('classes', 'classes.id', '=', 'sections.class_id')->where('classes.name', 'Grade 5')->where('sections.name', 'A')->select('sections.*')->first();
        $john = Student::where('first_name', 'John')->value('id');
        $rows = Enrollment::where('section_id', $section->id)->get(['id', 'student_id'])
            ->map(fn (Enrollment $enrollment): array => ['enrollment_id' => $enrollment->id, 'status' => $enrollment->student_id === $john ? 'absent' : 'present'])->all();

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance', ['section_id' => $section->id, 'date' => '2026-10-22', 'rows' => $rows])->assertOk();
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/erp/attendance', ['section_id' => $section->id, 'date' => '2026-10-22', 'rows' => $rows])->assertOk();

        $alerts = UserNotification::where('event_key', 'absent_alert')->get();
        $this->assertEqualsCanonicalizing([$this->parent->id, User::where('email', 'student@lms.test')->value('id')], $alerts->pluck('user_id')->all());
        $this->assertSame('Absent today: John Doe', $alerts->first()->title);
        $this->assertStringContainsString('Grade 5 A', $alerts->first()->body);
    }

    public function test_announcement_screens_need_the_permission_and_every_page_has_the_bell(): void
    {
        foreach (['admin.announcements.index', 'admin.announcements.categories', 'admin.announcements.templates', 'admin.announcements.delivery-log'] as $routeName) {
            $this->actingAs($this->admin)->get(route($routeName))->assertOk()->assertSee('js/resource-screen.js', false)->assertSee('js/admin-announcements.js', false);
            $this->actingAs($this->teacher)->get(route($routeName))->assertForbidden();
        }

        $this->actingAs($this->teacher)->get('/dashboard')->assertOk()->assertSee('data-bell', false)->assertSee(route('notices'));
    }

    public function test_message_templates_only_accept_known_events_and_deleting_an_announcement_keeps_it_soft_deleted(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/message-templates', ['name' => 'Bad', 'channel' => 'sms', 'event_key' => 'birthday', 'body' => 'Hi'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_key'], 'errors.fields');

        $announcement = $this->draft();
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/erp/announcements/{$announcement['id']}")->assertOk();

        $this->assertSoftDeleted('announcements', ['id' => $announcement['id']]);
        $this->assertSoftDeleted('announcement_audiences', ['announcement_id' => $announcement['id']]);
    }
}
