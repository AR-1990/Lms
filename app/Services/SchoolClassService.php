<?php

namespace App\Services;

use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SchoolClassService
{
    /**
     * @param  array{is_active?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return SchoolClass::query()
            ->select(['id', 'name', 'code', 'level_order', 'wing_id', 'next_class_id', 'is_active', 'created_at', 'updated_at'])
            ->with(['wing:id,name', 'nextClass:id,name'])
            ->withCount('sections')
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }

    /**
     * @param  array{is_active?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('level_order')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{is_active?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('wing_name', fn (SchoolClass $class): ?string => $class->wing?->name)
            ->addColumn('next_class_name', fn (SchoolClass $class): ?string => $class->nextClass?->name)
            ->removeColumn('wing', 'next_class')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SchoolClass
    {
        return DB::transaction(fn (): SchoolClass => SchoolClass::create([...$data, 'is_active' => $data['is_active'] ?? true])
            ->load(['wing:id,name', 'nextClass:id,name'])
            ->loadCount('sections'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SchoolClass $class, array $data): SchoolClass
    {
        return DB::transaction(function () use ($class, $data): SchoolClass {
            $class->update($data);

            return $class->load(['wing:id,name', 'nextClass:id,name'])->loadCount('sections');
        });
    }

    /**
     * @throws ValidationException when sections or curriculum still use the class
     */
    public function delete(SchoolClass $class): void
    {
        if ($class->sections()->exists() || $class->curriculum()->exists()) {
            throw ValidationException::withMessages(['class' => ['This class has sections or curriculum. Remove them first, or mark the class inactive.']]);
        }

        DB::transaction(function () use ($class): void {
            SchoolClass::where('next_class_id', $class->id)->get()->each(fn (SchoolClass $previous) => $previous->update(['next_class_id' => null]));
            $class->delete();
        });
    }
}
