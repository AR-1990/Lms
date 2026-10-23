<?php

namespace Tests\Feature;

use App\Events\TaskChanged;
use App\Models\Lookup;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskReminder;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function privateBoard(): array
    {
        return $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/task-boards', ['name' => 'Science fair', 'visibility' => 'private', 'members' => [['user_id' => $this->admin->id, 'role' => 'viewer']]])
            ->assertCreated()
            ->json('data');
    }

    public function test_a_board_gets_the_default_columns_and_assigning_a_task_notifies_and_schedules_a_reminder(): void
    {
        Event::fake([TaskChanged::class]);
        $board = $this->privateBoard();
        $this->assertSame(['To Do', 'In Progress', 'Review', 'Done'], collect($board['statuses'])->pluck('name')->all());

        $task = $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/tasks', [
                'title' => 'Prepare volcano model', 'task_board_id' => $board['id'], 'due_at' => '2026-10-25 12:00:00',
                'priority_id' => Lookup::where('type', 'task_priority')->where('name', 'High')->value('id'),
                'assignee_ids' => [$this->accountant->id], 'checklist' => [['title' => 'Buy clay']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status.name', 'To Do')
            ->json('data');

        $this->assertStringStartsWith('TSK-', $task['task_no']);
        $this->assertTrue(UserNotification::where('user_id', $this->accountant->id)->where('event_key', 'task_assigned')->exists());
        $this->assertSame('2026-10-24 12:00:00', TaskReminder::where('task_id', $task['id'])->value('remind_at')->toDateTimeString());
        Event::assertDispatched(TaskChanged::class, fn (TaskChanged $event): bool => $event->boardId === $board['id'] && in_array($this->accountant->id, $event->userIds, true));
        $this->assertDatabaseHas('activity_logs', ['module' => 'tasks', 'action' => 'assigned']);

        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(12, 5));
        $this->artisan('tasks:remind')->assertSuccessful();
        $this->assertTrue(UserNotification::where('user_id', $this->accountant->id)->where('event_key', 'task_due')->exists());
    }

    public function test_moving_to_done_completes_the_task_and_a_weekly_task_comes_back(): void
    {
        $board = $this->privateBoard();
        $columns = collect($board['statuses'])->pluck('id', 'name');
        $task = $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/tasks', ['title' => 'Lab safety check', 'task_board_id' => $board['id'], 'due_at' => '2026-10-23 09:00:00', 'recurrence' => 'weekly'])
            ->json('data');

        $this->actingAs($this->teacher, 'sanctum')
            ->patchJson("/api/erp/tasks/{$task['id']}/move", ['task_status_id' => $columns['Done'], 'sort_order' => 0])
            ->assertOk()
            ->assertJsonPath('message', 'Done! 🎉');

        $this->assertNotNull(Task::find($task['id'])->completed_at);
        $next = Task::where('title', 'Lab safety check')->whereNull('completed_at')->first();
        $this->assertSame('2026-10-30 09:00:00', $next->due_at->toDateTimeString());
        $this->assertSame($columns['To Do'], $next->task_status_id);
        $this->assertTrue($next->assignees()->where('user_id', $this->teacher->id)->exists());
        $this->assertDatabaseHas('activity_logs', ['module' => 'tasks', 'action' => 'completed']);

        $this->actingAs($this->teacher, 'sanctum')
            ->patchJson("/api/erp/tasks/{$task['id']}/move", ['task_status_id' => TaskBoard::where('name', 'Annual Sports Day 2026')->first()->statuses()->value('id')])
            ->assertUnprocessable();
    }

    public function test_private_boards_and_personal_tasks_stay_with_their_people_and_viewers_cannot_move_cards(): void
    {
        $board = $this->privateBoard();
        $task = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/tasks', ['title' => 'Judge list', 'task_board_id' => $board['id']])->json('data');
        $personal = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/erp/tasks', ['title' => 'Mark copies'])->json('data');

        $this->actingAs($this->accountant, 'sanctum')->getJson("/api/erp/task-boards/{$board['id']}")->assertNotFound();
        $this->actingAs($this->accountant, 'sanctum')->getJson("/api/erp/tasks/{$personal['id']}")->assertNotFound();
        $this->actingAs($this->accountant, 'sanctum')->getJson('/api/erp/task-boards')->assertJsonMissing(['name' => 'Science fair']);

        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('slug', 'teacher')->value('id'));
        TaskBoard::find($board['id'])->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);
        $this->actingAs($viewer, 'sanctum')->getJson("/api/erp/task-boards/{$board['id']}")->assertOk()->assertJsonPath('data.can_edit', false);
        $this->actingAs($viewer, 'sanctum')->patchJson("/api/erp/tasks/{$task['id']}/move", ['task_status_id' => $task['task_status_id']])->assertUnprocessable();

        $student = User::where('email', 'student@lms.test')->first();
        $this->actingAs($student, 'sanctum')->getJson('/api/erp/tasks')->assertForbidden();
        $this->actingAs($student)->get(route('tasks.index'))->assertForbidden();
    }

    public function test_comments_notify_the_people_on_the_task_and_checklists_tick(): void
    {
        $task = $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/tasks', ['title' => 'Print certificates', 'assignee_ids' => [$this->teacher->id, $this->accountant->id], 'checklist' => [['title' => 'Names list']]])
            ->json('data');

        $this->actingAs($this->accountant, 'sanctum')
            ->postJson("/api/erp/tasks/{$task['id']}/comments", ['body' => 'Paper is ordered.', 'mention_ids' => [$this->admin->id]])
            ->assertCreated();

        $notified = UserNotification::where('event_key', 'task_comment')->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing([$this->teacher->id, $this->admin->id], $notified);

        $this->actingAs($this->teacher, 'sanctum')
            ->patchJson("/api/erp/tasks/{$task['id']}/checklist/{$task['checklist'][0]['id']}")
            ->assertOk()
            ->assertJsonPath('data.is_done', true);

        $this->actingAs($this->teacher, 'sanctum')
            ->getJson('/api/erp/tasks/datatable?draw=1&state=open')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Print certificates', 'checklist_done_count' => 1]);
    }

    public function test_task_screens_open_for_staff(): void
    {
        $board = TaskBoard::where('name', 'Annual Sports Day 2026')->first();

        foreach ([route('tasks.index'), route('tasks.boards'), route('tasks.board', $board)] as $url) {
            $this->actingAs($this->teacher)->get($url)->assertOk()->assertSee('js/tasks.js', false);
        }

        $this->actingAs($this->teacher)->get(route('tasks.board', $board))->assertSee('js/realtime.js', false);
        $this->actingAs($this->teacher, 'sanctum')->getJson("/api/erp/task-boards/{$board->id}")->assertOk()->assertJsonCount(4, 'data.columns');
    }
}
