<?php

namespace App\Services;

use App\Models\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PeriodService
{
    public function query(): Builder
    {
        return Period::query()->select(['id', 'name', 'starts_at', 'ends_at', 'is_break', 'sort_order', 'created_at', 'updated_at']);
    }

    /**
     * The whole bell schedule is one short list, so the API returns it in order without paging.
     *
     * @return Collection<int, Period>
     */
    public function all(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderBy('starts_at')->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(): array
    {
        return DataTables::eloquent($this->query())
            ->escapeColumns([])
            ->editColumn('starts_at', fn (Period $period): string => substr((string) $period->starts_at, 0, 5))
            ->editColumn('ends_at', fn (Period $period): string => substr((string) $period->ends_at, 0, 5))
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Period
    {
        return DB::transaction(fn (): Period => Period::create([
            ...$data,
            'is_break' => $data['is_break'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Period $period, array $data): Period
    {
        return DB::transaction(function () use ($period, $data): Period {
            $period->update($data);

            return $period;
        });
    }

    /**
     * Timetable slots will block this once they exist (PLAN.md phase 8).
     */
    public function delete(Period $period): void
    {
        DB::transaction(fn () => $period->delete());
    }
}
