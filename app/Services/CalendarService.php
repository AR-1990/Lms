<?php

namespace App\Services;

use App\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * School calendar: holidays stop attendance being marked, public events appear on the website.
 */
class CalendarService
{
    /**
     * @param  array{from?: string|null, to?: string|null, event_type_id?: int|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return CalendarEvent::query()
            ->with('eventType:id,name')
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('ends_on', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('starts_on', '<=', $to))
            ->when($filters['event_type_id'] ?? null, fn (Builder $query, int $id) => $query->where('event_type_id', $id));
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('event_type_name', fn (CalendarEvent $event): ?string => $event->eventType?->name)
            ->editColumn('starts_on', fn (CalendarEvent $event): string => $event->starts_on->toDateString())
            ->editColumn('ends_on', fn (CalendarEvent $event): string => $event->ends_on->toDateString())
            ->removeColumn('event_type')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?CalendarEvent $event, array $data): CalendarEvent
    {
        $data['ends_on'] ??= $data['starts_on'];

        return DB::transaction(function () use ($event, $data): CalendarEvent {
            if ($event === null) {
                $event = CalendarEvent::create($data);
            } else {
                $event->update($data);
            }

            return $event->load('eventType:id,name');
        });
    }

    public function delete(CalendarEvent $event): void
    {
        DB::transaction(fn () => $event->delete());
    }

    public function holidayOn(string $date): ?CalendarEvent
    {
        return CalendarEvent::where('is_holiday', true)->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->first(['id', 'title']);
    }

    /**
     * Next public events for the website.
     *
     * @return Collection<int, CalendarEvent>
     */
    public function upcomingPublic(int $limit = 3): Collection
    {
        return CalendarEvent::where('is_public', true)->whereDate('ends_on', '>=', now()->toDateString())->orderBy('starts_on')->limit($limit)
            ->get(['id', 'title', 'starts_on', 'ends_on', 'start_time', 'end_time', 'venue', 'description']);
    }
}
