<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only: activity logs are append-only, so there is no update or delete endpoint.
 */
class ActivityLogController extends Controller
{
    use ApiResponse;

    public function __construct(private ActivityLogService $activityLogService) {}

    /**
     * ?module=&action=&user_id=&date_from=Y-m-d&date_to=Y-m-d&search=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate($this->filterRules());

        $logs = $this->activityLogService->paginate($this->filters($request), $request->integer('per_page', 20));

        return $this->paginatedResponse($logs, ActivityLogResource::class, 'Activity log retrieved.');
    }

    /**
     * The grid's global search box (DataTables `search[value]`) is applied by Yajra itself.
     */
    public function datatable(Request $request): JsonResponse
    {
        $request->validate(collect($this->filterRules())->except('search')->all());

        return $this->successResponse($this->activityLogService->datatable($this->filters($request)), 'Activity log retrieved.');
    }

    public function filterOptions(): JsonResponse
    {
        return $this->successResponse($this->activityLogService->filterOptions(), 'Filter options retrieved.');
    }

    public function show(ActivityLog $activityLog): JsonResponse
    {
        return $this->successResponse(new ActivityLogResource($this->activityLogService->find($activityLog)), 'Activity retrieved.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function filterRules(): array
    {
        return [
            'module' => ['nullable', 'string', 'max:40'],
            'action' => ['nullable', 'string', 'max:30'],
            'user_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return collect($request->only(['module', 'action', 'user_id', 'date_from', 'date_to', 'search']))
            ->filter(fn (mixed $value): bool => is_string($value) || is_int($value))
            ->all();
    }
}
