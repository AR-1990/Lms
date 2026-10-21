<?php

namespace App\Services;

use App\Models\Campus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class CampusService
{
    /**
     * @param  array{is_active?: string|null, search?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Campus::query()
            ->select(['id', 'name', 'code', 'address', 'city', 'phone', 'email', 'is_active', 'created_at', 'updated_at'])
            ->withCount('users')
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('code', 'like', '%'.$filters['search'].'%')
                ->orWhere('city', 'like', '%'.$filters['search'].'%')));
    }

    /**
     * @param  array{is_active?: string|null, search?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('name')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{is_active?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))->escapeColumns([])->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Campus
    {
        return DB::transaction(fn (): Campus => Campus::create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => request()->user()?->getKey(),
            'updated_by' => request()->user()?->getKey(),
        ])->loadCount('users'));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when this would leave the school without an active campus
     */
    public function update(Campus $campus, array $data): Campus
    {
        if ($campus->is_active && array_key_exists('is_active', $data) && ! $data['is_active'] && $this->isLastActive($campus)) {
            throw ValidationException::withMessages([
                'is_active' => ['At least one campus must stay active.'],
            ]);
        }

        return DB::transaction(function () use ($campus, $data): Campus {
            $campus->update([...$data, 'updated_by' => request()->user()?->getKey()]);

            return $campus->refresh()->loadCount('users');
        });
    }

    /**
     * @throws ValidationException when the campus still has users or is the last active one
     */
    public function delete(Campus $campus): void
    {
        $userCount = $campus->users()->count();

        if ($userCount > 0) {
            throw ValidationException::withMessages([
                'campus' => ["This campus still has {$userCount} ".str('user')->plural($userCount).'. Move them to another campus first.'],
            ]);
        }

        if ($campus->is_active && $this->isLastActive($campus)) {
            throw ValidationException::withMessages([
                'campus' => ['The last active campus cannot be deleted.'],
            ]);
        }

        DB::transaction(fn () => $campus->delete());
    }

    private function isLastActive(Campus $campus): bool
    {
        return ! Campus::query()->where('is_active', true)->whereKeyNot($campus->getKey())->exists();
    }
}
