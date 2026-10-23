<?php

namespace Tests\Feature;

use App\Events\ChatEvent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageReceipt;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ChatService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->accountant = User::where('email', 'accounts@lms.test')->first();
    }

    private function direct(User $from, User $to): int
    {
        return $this->actingAs($from, 'sanctum')->postJson('/api/erp/chat/direct', ['user_id' => $to->id])->assertCreated()->json('data.id');
    }

    public function test_a_direct_chat_is_reused_and_messages_go_out_live_with_ticks(): void
    {
        Event::fake([ChatEvent::class]);
        $chatId = $this->direct($this->teacher, $this->accountant);
        $this->assertSame($chatId, $this->direct($this->accountant, $this->teacher));

        $uuid = '7b0f1a52-4d6e-4c8b-9e4a-2f0c3b1d5e61';
        $message = $this->actingAs($this->teacher, 'sanctum')
            ->postJson("/api/erp/chat/conversations/{$chatId}/messages", ['body' => 'Fee list is ready', 'client_uuid' => $uuid])
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent')
            ->json('data');

        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/chat/conversations/{$chatId}/messages", ['body' => 'Fee list is ready', 'client_uuid' => $uuid])->assertCreated()->assertJsonPath('data.id', $message['id']);
        $this->assertSame(1, ChatMessage::where('conversation_id', $chatId)->count());
        Event::assertDispatched(ChatEvent::class, fn (ChatEvent $event): bool => $event->name === 'message.sent' && $event->channels === ['chat.'.$chatId]);

        $this->actingAs($this->accountant, 'sanctum')->getJson('/api/erp/chat/conversations')->assertJsonFragment(['id' => $chatId, 'unread_count' => 1, 'name' => $this->teacher->name]);
        $this->actingAs($this->accountant, 'sanctum')->getJson("/api/erp/chat/conversations/{$chatId}/messages")->assertOk()->assertJsonPath('data.0.body', 'Fee list is ready');
        $this->assertNotNull(ChatMessageReceipt::where('message_id', $message['id'])->value('delivered_at'));

        $this->actingAs($this->accountant, 'sanctum')->postJson("/api/erp/chat/conversations/{$chatId}/read", ['up_to_id' => $message['id']])->assertOk();
        $this->actingAs($this->teacher, 'sanctum')->getJson("/api/erp/chat/conversations/{$chatId}/messages")->assertJsonPath('data.0.status', 'read');
        $this->actingAs($this->accountant, 'sanctum')->getJson('/api/erp/chat/unread')->assertJsonPath('data.unread', 0);

        $this->actingAs($this->admin, 'sanctum')->getJson("/api/erp/chat/conversations/{$chatId}/messages")->assertNotFound();
        $this->actingAs(User::where('email', 'parent@lms.test')->first(), 'sanctum')->getJson('/api/erp/chat/conversations')->assertForbidden();
    }

    public function test_replies_mentions_reactions_edits_and_deletes(): void
    {
        $group = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/chat/groups', ['name' => 'Exam committee', 'member_ids' => [$this->teacher->id, $this->accountant->id]])
            ->assertCreated()
            ->assertJsonPath('data.my_role', 'admin')
            ->json('data.id');

        $first = $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/chat/conversations/{$group}/messages", ['body' => 'Papers printed'])->json('data');
        $reply = $this->actingAs($this->accountant, 'sanctum')
            ->postJson("/api/erp/chat/conversations/{$group}/messages", ['body' => '@Admin bills attached', 'reply_to_message_id' => $first['id'], 'mention_ids' => [$this->admin->id]])
            ->assertCreated()
            ->assertJsonPath('data.reply_to.body', 'Papers printed')
            ->json('data');
        $this->assertTrue(UserNotification::where('user_id', $this->admin->id)->where('event_key', 'chat_mention')->exists());

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/chat/messages/{$reply['id']}/react", ['emoji' => '👍'])->assertOk()->assertJsonPath('data.reactions.0.user_ids', [$this->admin->id]);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/chat/messages/{$reply['id']}/react", ['emoji' => null])->assertOk()->assertJsonPath('data.reactions', []);

        $this->actingAs($this->teacher, 'sanctum')->putJson("/api/erp/chat/messages/{$first['id']}", ['body' => 'Papers printed and sealed'])->assertOk()->assertJsonPath('data.is_edited', true);
        $this->actingAs($this->accountant, 'sanctum')->putJson("/api/erp/chat/messages/{$first['id']}", ['body' => 'hack'])->assertUnprocessable();
        $this->travel(20)->minutes();
        $this->actingAs($this->teacher, 'sanctum')->putJson("/api/erp/chat/messages/{$first['id']}", ['body' => 'late edit'])->assertUnprocessable();

        $this->actingAs($this->accountant, 'sanctum')->deleteJson("/api/erp/chat/messages/{$first['id']}", ['for_everyone' => true])->assertUnprocessable();
        $this->actingAs($this->accountant, 'sanctum')->deleteJson("/api/erp/chat/messages/{$first['id']}", ['for_everyone' => false])->assertOk();
        $visible = $this->actingAs($this->accountant, 'sanctum')->getJson("/api/erp/chat/conversations/{$group}/messages")->json('data.*.id');
        $this->assertNotContains($first['id'], $visible);

        $this->actingAs($this->teacher, 'sanctum')->deleteJson("/api/erp/chat/messages/{$first['id']}", ['for_everyone' => true])->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/erp/chat/conversations/{$group}/messages")->assertJsonFragment(['id' => $first['id'], 'body' => null, 'is_deleted' => true]);
        $this->assertSame('Papers printed and sealed', ChatMessage::find($first['id'])->body);
    }

    public function test_files_search_forward_and_announcement_only_groups(): void
    {
        Storage::fake('local');
        $chatId = $this->direct($this->teacher, $this->admin);

        $photo = $this->actingAs($this->teacher, 'sanctum')
            ->post("/api/erp/chat/conversations/{$chatId}/messages", ['files' => [UploadedFile::fake()->image('board.jpg')]], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'image')
            ->json('data');
        $this->assertStringContainsString('signature=', $photo['attachments'][0]['url']);

        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/chat/conversations/{$chatId}/messages", ['body' => 'Timetable draft v2']);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/erp/chat/search?q=draft')->assertOk()->assertJsonPath('data.0.body', 'Timetable draft v2');

        $notice = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/erp/chat/groups', ['name' => 'Principal notices', 'member_ids' => [$this->teacher->id], 'only_admins_can_post' => true])
            ->json('data.id');
        $this->actingAs($this->teacher, 'sanctum')->postJson("/api/erp/chat/conversations/{$notice}/messages", ['body' => 'hi'])->assertUnprocessable();

        $message = ChatMessage::where('body', 'Timetable draft v2')->first();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/erp/chat/messages/{$message->id}/forward", ['conversation_ids' => [$notice]])->assertOk();
        $this->assertTrue(ChatMessage::where('conversation_id', $notice)->where('body', 'Timetable draft v2')->whereNotNull('forwarded_from_message_id')->exists());
    }

    public function test_department_groups_follow_hr(): void
    {
        $department = Department::whereHas('employees', fn ($query) => $query->whereNotNull('user_id'))->first() ?? Department::first();
        $employee = Employee::whereNotNull('user_id')->first();
        $employee->update(['department_id' => $department->id]);

        $this->artisan('chat:sync-departments')->assertSuccessful();
        $group = ChatConversation::where('department_id', $department->id)->first();
        $this->assertTrue($group->participants()->where('user_id', $employee->user_id)->whereNull('left_at')->exists());

        $employee->update(['department_id' => Department::whereKeyNot($department->id)->value('id')]);
        $this->artisan('chat:sync-departments')->assertSuccessful();
        $this->assertNotNull($group->participants()->where('user_id', $employee->user_id)->value('left_at'));
    }

    public function test_chat_page_and_channel_rules(): void
    {
        $this->actingAs($this->teacher)->get(route('chat'))->assertOk()->assertSee('js/chat.js', false)->assertSee('js/realtime.js', false);
        $this->actingAs(User::where('email', 'student@lms.test')->first())->get(route('chat'))->assertForbidden();

        $chatId = $this->direct($this->teacher, $this->accountant);
        $service = app(ChatService::class);
        $this->assertTrue($service->canJoin($this->teacher, $chatId));
        $outsider = User::factory()->create();
        $outsider->roles()->attach(Role::where('slug', 'teacher')->value('id'));
        $this->assertFalse($service->canJoin($outsider, $chatId));
        // The admin may listen through Chat Monitor (monitor-chat) without being a member.
        $this->assertTrue($service->canJoin($this->admin, $chatId));
    }
}
