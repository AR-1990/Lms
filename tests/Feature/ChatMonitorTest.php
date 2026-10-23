<?php

namespace Tests\Feature;

use App\Events\ChatEvent;
use App\Events\NotificationSent;
use App\Models\ActivityLog;
use App\Models\ChatMessage;
use App\Models\ChatMessageReceipt;
use App\Models\ChatParticipant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ChatService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatMonitorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ChatEvent::class, NotificationSent::class]);
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->accountant = User::where('email', 'accounts@lms.test')->first();
    }

    private function teacherGroup(): int
    {
        return $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/chat/groups', ['name' => 'Exam Duty', 'member_ids' => [$this->accountant->id]])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_admin_is_notified_when_an_employee_creates_a_group(): void
    {
        $groupId = $this->teacherGroup();

        $notification = UserNotification::where('user_id', $this->admin->id)->where('event_key', 'chat_group_created')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Exam Duty', $notification->title);
        $this->assertStringContainsString('conversation='.$groupId, (string) $notification->url);
        $this->assertFalse(UserNotification::where('user_id', $this->teacher->id)->where('event_key', 'chat_group_created')->exists());
        Event::assertDispatched(NotificationSent::class, fn (NotificationSent $event): bool => in_array($this->admin->id, $event->userIds, true)
            && $event->payload['event_key'] === 'chat_group_created');
    }

    public function test_new_messages_reach_members_live_and_muted_members_silently(): void
    {
        $groupId = $this->teacherGroup();
        ChatParticipant::where('conversation_id', $groupId)->where('user_id', $this->accountant->id)->update(['muted_until' => now()->addDay()]);

        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/chat/conversations/{$groupId}/messages", ['body' => 'Hall 3'])->assertCreated();

        Event::assertDispatched(ChatEvent::class, fn (ChatEvent $event): bool => $event->name === 'conversation.updated'
            && $event->channels === ['App.Models.User.'.$this->accountant->id] && $event->payload['is_muted'] === true && $event->payload['is_new'] === true);
        Event::assertDispatched(ChatEvent::class, fn (ChatEvent $event): bool => $event->name === 'conversation.updated'
            && in_array('App.Models.User.'.$this->teacher->id, $event->channels, true) && $event->payload['is_muted'] === false);
    }

    public function test_admin_reads_any_chat_without_joining_or_leaving_ticks(): void
    {
        $groupId = $this->teacherGroup();
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/chat/conversations/{$groupId}/messages", ['body' => 'Hall 3 at 9am'])->assertCreated();
        $deleted = $this->postJson("/api/erp/chat/conversations/{$groupId}/messages", ['body' => 'Wrong hall'])->json('data.id');
        $this->deleteJson("/api/erp/chat/messages/{$deleted}", ['for_everyone' => true])->assertOk();
        $lastSeen = $this->admin->fresh()->last_seen_at;

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/chat-monitor/datatable?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Exam Duty']);
        $this->getJson("/api/erp/chat-monitor/conversations/{$groupId}")->assertOk()->assertJsonCount(2, 'data.participants');
        $messages = $this->getJson("/api/erp/chat-monitor/conversations/{$groupId}/messages")->assertOk()->json('data');

        $this->assertContains('Hall 3 at 9am', array_column($messages, 'body'));
        $this->assertContains('Wrong hall', array_column($messages, 'body'));
        $this->assertFalse(ChatParticipant::where('conversation_id', $groupId)->where('user_id', $this->admin->id)->exists());
        $this->assertFalse(ChatMessageReceipt::where('user_id', $this->admin->id)->whereIn('message_id', ChatMessage::where('conversation_id', $groupId)->select('id'))->exists());
        $this->assertEquals($lastSeen, $this->admin->fresh()->last_seen_at);
        $this->assertTrue(app(ChatService::class)->canJoin($this->admin, $groupId));
        $this->assertTrue(ActivityLog::where('module', 'chat')->where('action', 'viewed')->where('user_id', $this->admin->id)->exists());
    }

    public function test_admin_adds_and_removes_members_of_any_staff_group(): void
    {
        $groupId = $this->teacherGroup();
        $other = User::where('is_active', true)->whereNotIn('id', [$this->admin->id, $this->teacher->id, $this->accountant->id])->first();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/erp/chat-monitor/conversations/{$groupId}/members", ['add_ids' => [$other->id], 'remove_ids' => [$this->accountant->id]])
            ->assertOk()
            ->assertJsonPath('success', true);

        $active = ChatParticipant::where('conversation_id', $groupId)->whereNull('left_at')->pluck('user_id')->all();
        $this->assertContains($other->id, $active);
        $this->assertNotContains($this->accountant->id, $active);
        $this->assertNotContains($this->admin->id, $active);
    }

    public function test_chat_screen_works_when_the_site_is_opened_from_another_address(): void
    {
        $this->actingAs($this->teacher)
            ->withServerVariables(['HTTP_HOST' => '192.168.100.178:8003'])
            ->withHeaders(['Referer' => 'http://192.168.100.178:8003/chat'])
            ->getJson('http://192.168.100.178:8003/api/erp/chat/people')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_makes_a_member_group_admin_and_back(): void
    {
        $groupId = $this->teacherGroup();
        $roleOf = fn (): string => ChatParticipant::where('conversation_id', $groupId)->where('user_id', $this->accountant->id)->value('role');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/erp/chat-monitor/conversations/{$groupId}/members", ['make_admin_ids' => [$this->accountant->id]])
            ->assertOk()
            ->assertJsonFragment(['user_id' => $this->accountant->id, 'role' => 'admin']);
        $this->assertSame('admin', $roleOf());
        $this->assertTrue(ChatMessage::where('conversation_id', $groupId)->where('type', 'system')->where('body', 'like', '%made%group admin')->exists());

        $this->putJson("/api/erp/chat-monitor/conversations/{$groupId}/members", ['make_member_ids' => [$this->accountant->id]])->assertOk();
        $this->assertSame('member', $roleOf());
    }

    public function test_employees_cannot_use_the_chat_monitor(): void
    {
        $groupId = $this->teacherGroup();

        $this->actingAs($this->accountant, 'sanctum')->getJson("/api/erp/chat-monitor/conversations/{$groupId}/messages")->assertForbidden()->assertJsonPath('success', false);
        $this->putJson("/api/erp/chat-monitor/conversations/{$groupId}/members", ['add_ids' => [$this->accountant->id]])->assertForbidden();
        $this->actingAs($this->accountant)->get(route('admin.chat-monitor'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.chat-monitor'))->assertOk()->assertSee('data-monitor', false);
    }
}
