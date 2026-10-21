<?php

namespace App\Services;

use App\Models\Lookup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class LookupService
{
    /**
     * Filtered query shared by the paginated list (mobile) and the DataTables grid (web).
     *
     * @param  array{type?: string|null, is_active?: bool|string|null, search?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Lookup::query()
            ->select(['id', 'type', 'name', 'code', 'description', 'sort_order', 'is_active', 'is_system', 'created_at', 'updated_at'])
            ->when(filled($filters['type'] ?? null), fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('code', 'like', '%'.$filters['search'].'%')));
    }

    /**
     * @param  array{type?: string|null, is_active?: bool|string|null, search?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Server-side DataTables payload (draw, recordsTotal, recordsFiltered, data).
     *
     * @param  array{type?: string|null, is_active?: bool|string|null, search?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        // Values are escaped once, by the browser grid renderers — not here as well.
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('type_label', fn (Lookup $lookup): string => $this->typeLabel($lookup->type))
            ->toArray();
    }

    /**
     * Every list type with its option count, for the type filter and the "add option" form.
     *
     * @return SupportCollection<int, array{type: string, label: string, total: int, active: int}>
     */
    public function types(): SupportCollection
    {
        return Lookup::query()
            ->select('type')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_active then 1 else 0 end) as active')
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->map(fn (Lookup $row): array => [
                'type' => $row->type,
                'label' => $this->typeLabel($row->type),
                'total' => (int) $row->total,
                'active' => (int) $row->active,
            ]);
    }

    /**
     * Active options of one list, for dropdowns in any form (web and mobile).
     *
     * @return Collection<int, Lookup>
     */
    public function options(string $type): Collection
    {
        return Lookup::query()->ofType($type)->active()->get(['id', 'type', 'name', 'code', 'description', 'sort_order']);
    }

    /**
     * Today's dashboard quote, rotating daily through the active "daily_quote" options.
     *
     * @return array{text: string, by: string|null}|null
     */
    public function dailyQuote(): ?array
    {
        $quotes = $this->options('daily_quote');

        if ($quotes->isEmpty()) {
            return null;
        }

        $quote = $quotes->values()->get(now()->dayOfYear % $quotes->count());

        return ['text' => $quote->name, 'by' => $quote->description];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Lookup
    {
        return DB::transaction(fn (): Lookup => Lookup::create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'is_system' => false,
            'created_by' => request()->user()?->getKey(),
            'updated_by' => request()->user()?->getKey(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lookup $lookup, array $data): Lookup
    {
        return DB::transaction(function () use ($lookup, $data): Lookup {
            $lookup->update([...$data, 'updated_by' => request()->user()?->getKey()]);

            return $lookup->refresh();
        });
    }

    /**
     * @throws ValidationException when the option is a built-in system value
     */
    public function delete(Lookup $lookup): void
    {
        if ($lookup->is_system) {
            throw ValidationException::withMessages([
                'lookup' => ['Built-in options cannot be deleted. Deactivate it instead.'],
            ]);
        }

        DB::transaction(fn () => $lookup->delete());
    }

    public function typeLabel(string $type): string
    {
        return Str::headline($type);
    }
}
