<?php

namespace App\Services;

use App\Models\StickyNote;
use App\Models\StickyNoteItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Personal sticky notes: only the owner ever sees, changes or deletes them, so a delete needs no
 * approval (it is still a soft delete and logged). Reminders go to the bell; done notes archive after 7 days.
 */
class StickyNoteService
{
    public function __construct(private NotificationService $notificationService) {}

    /**
     * @param  string  $view  active (default), done or archived
     */
    public function forUser(User $user, string $view = 'active'): Builder
    {
        return StickyNote::query()
            ->where('user_id', $user->id)
            ->with(['color:id,name,code', 'items'])
            ->when($view === 'active', fn (Builder $query) => $query->where('is_done', false))
            ->when($view === 'done', fn (Builder $query) => $query->where('is_done', true)->whereNull('archived_at'))
            ->when($view === 'archived', fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    public function find(User $user, int $id): StickyNote
    {
        return StickyNote::where('user_id', $user->id)->with(['color:id,name,code', 'items'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data  note fields + optional items[{text, is_done}]
     */
    public function save(User $user, ?StickyNote $note, array $data): StickyNote
    {
        return DB::transaction(function () use ($user, $note, $data): StickyNote {
            $fields = collect($data)->except('items')->all();

            if (array_key_exists('remind_at', $fields) && $note?->remind_at?->toDateTimeString() !== $fields['remind_at']) {
                $fields['reminded_at'] = null;
            }

            if ($note === null) {
                $note = StickyNote::create([...$fields, 'user_id' => $user->id, 'sort_order' => (int) StickyNote::where('user_id', $user->id)->min('sort_order') - 1]);
            } else {
                $note->update($fields);
            }

            if (array_key_exists('items', $data)) {
                $note->items()->get()->each->delete();

                foreach (array_values($data['items'] ?? []) as $position => $item) {
                    $note->items()->create(['text' => $item['text'], 'is_done' => (bool) ($item['is_done'] ?? false), 'done_at' => ($item['is_done'] ?? false) ? now() : null, 'sort_order' => $position]);
                }
            }

            return $this->find($user, $note->id);
        });
    }

    public function setDone(StickyNote $note, bool $done): StickyNote
    {
        $note->update(['is_done' => $done, 'done_at' => $done ? now() : null, 'archived_at' => null]);

        return $note;
    }

    public function setPinned(StickyNote $note, bool $pinned): StickyNote
    {
        $note->update(['is_pinned' => $pinned]);

        return $note;
    }

    public function toggleItem(StickyNoteItem $item): StickyNoteItem
    {
        $item->update(['is_done' => ! $item->is_done, 'done_at' => $item->is_done ? null : now()]);

        return $item;
    }

    /**
     * Drag-and-drop order: the ids in their new order (only the owner's notes move).
     *
     * @param  array<int, int>  $ids
     */
    public function reorder(User $user, array $ids): void
    {
        DB::transaction(function () use ($user, $ids): void {
            foreach (array_values($ids) as $position => $id) {
                StickyNote::where('user_id', $user->id)->whereKey($id)->update(['sort_order' => $position]);
            }
        });
    }

    public function delete(StickyNote $note): void
    {
        DB::transaction(function () use ($note): void {
            $note->items()->get()->each->delete();
            $note->delete();
        });
    }

    /**
     * Scheduler: bell reminders that are due, and archiving of notes done more than 7 days ago.
     *
     * @return array{0: int, 1: int}
     */
    public function remindDue(): array
    {
        $due = StickyNote::where('is_done', false)->whereNull('reminded_at')->whereNotNull('remind_at')->where('remind_at', '<=', now())->get();

        foreach ($due as $note) {
            $this->notificationService->notify([$note->user_id], 'sticky_note', 'Reminder: '.($note->title ?: 'sticky note'), $note->body, route('dashboard'), $note, false, ['in_app', 'push']);
            $note->forceFill(['reminded_at' => now()])->saveQuietly();
        }

        $archived = StickyNote::where('is_done', true)->whereNull('archived_at')->where('done_at', '<=', now()->subDays(7))->update(['archived_at' => now()]);

        return [$due->count(), $archived];
    }
}
