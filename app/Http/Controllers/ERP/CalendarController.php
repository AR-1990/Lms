<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveCalendarEventRequest;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Services\CalendarService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private CalendarService $calendarService) {}

    /**
     * Events in a date range for everyone signed in; role-only events show to that role.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $roleIds = $request->user()->roles()->pluck('roles.id');
        $events = $this->calendarService->query($filters)
            ->where(fn (Builder $query) => $query->whereNull('audience_role_id')->orWhereIn('audience_role_id', $roleIds))
            ->orderBy('starts_on')
            ->limit(500)
            ->get();

        return $this->successResponse(CalendarEventResource::collection($events)->resolve(), 'Calendar retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->calendarService->datatable($this->filters($request)), 'Calendar retrieved.');
    }

    public function store(SaveCalendarEventRequest $request): JsonResponse
    {
        return $this->successResponse(new CalendarEventResource($this->calendarService->save(null, $request->validated())), 'Added to the calendar.', 201);
    }

    public function update(SaveCalendarEventRequest $request, CalendarEvent $calendarEvent): JsonResponse
    {
        return $this->successResponse(new CalendarEventResource($this->calendarService->save($calendarEvent, $request->validated())), 'Calendar updated.');
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        return $this->deleteWithApproval($calendarEvent, 'Removed from the calendar.');
    }

    /**
     * @return array{from: string|null, to: string|null, event_type_id: int|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'event_type_id' => ['nullable', 'integer'],
        ]);

        return [
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'event_type_id' => isset($validated['event_type_id']) ? (int) $validated['event_type_id'] : null,
        ];
    }
}
