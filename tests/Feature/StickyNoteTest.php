<?php

namespace Tests\Feature;

use App\Models\Lookup;
use App\Models\StickyNote;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StickyNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $this->teacher = User::where('email', 'teacher@lms.test')->first();
        $this->parent = User::where('email', 'parent@lms.test')->first();
    }

    public function test_a_user_keeps_private_notes_with_a_checklist(): void
    {
        $note = $this->actingAs($this->teacher, 'sanctum')
            ->postJson('/api/erp/sticky-notes', [
                'title' => 'Grade 5',
                'body' => 'Submit marks by Friday',
                'color_id' => Lookup::where('type', 'sticky_note_color')->where('name', 'Mint')->value('id'),
                'items' => [['text' => 'Maths'], ['text' => 'English', 'is_done' => true]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.color', '#d4f5e3')
            ->assertJsonCount(2, 'data.items')
            ->json('data');

        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/sticky-notes')->assertOk()->assertJsonPath('data.items.0.id', $note['id']);
        $this->actingAs($this->parent, 'sanctum')->getJson('/api/erp/sticky-notes')->assertOk()->assertJsonPath('data.meta.total', 0);

        foreach (['putJson' => ['title' => 'x'], 'patchJson' => ['is_done' => true], 'deleteJson' => []] as $method => $body) {
            $url = '/api/erp/sticky-notes/'.$note['id'].($method === 'patchJson' ? '/done' : '');
            $this->actingAs($this->parent, 'sanctum')->{$method}($url, $body)->assertNotFound();
        }

        $this->actingAs($this->teacher, 'sanctum')
            ->patchJson("/api/erp/sticky-notes/{$note['id']}/items/{$note['items'][0]['id']}")
            ->assertOk()
            ->assertJsonPath('data.is_done', true);

        $this->actingAs($this->teacher, 'sanctum')->deleteJson("/api/erp/sticky-notes/{$note['id']}")->assertOk();
        $this->assertSoftDeleted('sticky_notes', ['id' => $note['id']]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'sticky_notes', 'action' => 'deleted']);
    }

    public function test_notes_are_reordered_pinned_and_done_notes_leave_the_board(): void
    {
        $first = StickyNote::factory()->for($this->teacher)->create(['sort_order' => 0]);
        $second = StickyNote::factory()->for($this->teacher)->create(['sort_order' => 1]);
        $someoneElses = StickyNote::factory()->for($this->parent)->create(['sort_order' => 5]);

        $this->actingAs($this->teacher, 'sanctum')->putJson('/api/erp/sticky-notes/reorder', ['ids' => [$second->id, $first->id, $someoneElses->id]])->assertOk();
        $this->assertSame([1, 0, 5], [$first->refresh()->sort_order, $second->refresh()->sort_order, $someoneElses->refresh()->sort_order]);

        $this->actingAs($this->teacher, 'sanctum')->patchJson("/api/erp/sticky-notes/{$first->id}/pin", ['is_pinned' => true])->assertOk();
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/sticky-notes')->assertJsonPath('data.items.0.id', $first->id);

        $this->actingAs($this->teacher, 'sanctum')->patchJson("/api/erp/sticky-notes/{$second->id}/done", ['is_done' => true])->assertOk();
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/sticky-notes')->assertJsonPath('data.meta.total', 1);
        $this->actingAs($this->teacher, 'sanctum')->getJson('/api/erp/sticky-notes?view=done')->assertJsonPath('data.items.0.id', $second->id);
    }

    public function test_due_reminders_ring_the_bell_once_and_old_done_notes_are_archived(): void
    {
        $note = StickyNote::factory()->for($this->teacher)->create(['title' => 'Call Ali\'s father', 'remind_at' => now()->addMinutes(5)]);
        $done = StickyNote::factory()->for($this->teacher)->create(['is_done' => true, 'done_at' => now()]);

        $this->artisan('sticky-notes:remind')->assertSuccessful();
        $this->assertFalse(UserNotification::where('event_key', 'sticky_note')->exists());

        $this->travel(10)->minutes();
        $this->artisan('sticky-notes:remind')->assertSuccessful();
        $this->artisan('sticky-notes:remind')->assertSuccessful();

        $this->assertSame(1, UserNotification::where('user_id', $this->teacher->id)->where('event_key', 'sticky_note')->count());
        $this->assertSame("Reminder: Call Ali's father", UserNotification::where('event_key', 'sticky_note')->value('title'));
        $this->assertNotNull($note->refresh()->reminded_at);

        $this->travel(8)->days();
        $this->artisan('sticky-notes:remind')->assertSuccessful();
        $this->assertNotNull($done->refresh()->archived_at);
    }

    public function test_every_dashboard_shows_the_notes_board(): void
    {
        foreach (['admin@lms.test', 'teacher@lms.test', 'parent@lms.test', 'student@lms.test', 'accounts@lms.test'] as $email) {
            $this->actingAs(User::where('email', $email)->first())->get('/dashboard')->assertOk()->assertSee('data-sticky-board', false);
        }

        $this->actingAs($this->teacher)->get(route('my-notes.done'))->assertOk()->assertSee('data-view="done"', false);
    }
}
