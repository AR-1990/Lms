<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class ActivityLogService
{
    /**
     * Filtered, newest-first query shared by the paginated list and the DataTables grid.
     *
     * @param  array{module?: string|null, action?: string|null, user_id?: int|null, date_from?: string|null, date_to?: string|null, search?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return ActivityLog::query()
            ->when(filled($filters['module'] ?? null), fn (Builder $query) => $query->where('module', $filters['module']))
            ->when(filled($filters['action'] ?? null), fn (Builder $query) => $query->where('action', $filters['action']))
            ->when(filled($filters['user_id'] ?? null), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->where('created_at', '>=', $filters['date_from'].' 00:00:00'))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->where('created_at', '<=', $filters['date_to'].' 23:59:59'))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('description', 'like', '%'.$filters['search'].'%')
                ->orWhere('subject_label', 'like', '%'.$filters['search'].'%')
                ->orWhere('user_name', 'like', '%'.$filters['search'].'%')));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($filters)->latest('created_at')->latest('id')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        // Values are escaped once, by the browser grid renderers — not here as well.
        return DataTables::eloquent($this->query($filters)->withCount('changes'))
            ->escapeColumns([])
            ->addColumn('module_label', fn (ActivityLog $log): string => Str::headline($log->module))
            ->addColumn('action_label', fn (ActivityLog $log): string => Str::headline($log->action))
            ->editColumn('created_at', fn (ActivityLog $log): ?string => $log->created_at?->toIso8601String())
            ->toArray();
    }

    public function find(ActivityLog $activityLog): ActivityLog
    {
        return $activityLog->load('changes');
    }

    /**
     * Modules and actions that actually appear in the log, for the filter dropdowns.
     *
     * @return array{modules: array<int, array{value: string, label: string}>, actions: array<int, array{value: string, label: string}>}
     */
    public function filterOptions(): array
    {
        $toOptions = fn (string $column): array => ActivityLog::query()
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn (string $value): array => ['value' => $value, 'label' => Str::headline($value)])
            ->all();

        return [
            'modules' => $toOptions('module'),
            'actions' => $toOptions('action'),
        ];
    }
}
