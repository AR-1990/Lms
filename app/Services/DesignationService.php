<?php

namespace App\Services;

use App\Models\Designation;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class DesignationService
{
    /**
     * @param  array{is_active?: string|null, department_id?: int|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Designation::query()
            ->select(['id', 'name', 'department_id', 'grade_id', 'is_teaching', 'is_active', 'created_at', 'updated_at'])
            ->with(['department:id,name', 'grade:id,name'])
            ->withCount('employees')
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $departmentId) => $query->where('department_id', $departmentId))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }

    /**
     * @param  array{is_active?: string|null, department_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('department_name', fn (Designation $designation): ?string => $designation->department?->name)
            ->addColumn('grade_name', fn (Designation $designation): ?string => $designation->grade?->name)
            ->removeColumn('department', 'grade')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Designation
    {
        return DB::transaction(fn (): Designation => Designation::create([
            ...$data,
            'is_teaching' => $data['is_teaching'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ])->load(['department:id,name', 'grade:id,name'])->loadCount('employees'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Designation $designation, array $data): Designation
    {
        return DB::transaction(function () use ($designation, $data): Designation {
            $designation->update($data);

            return $designation->load(['department:id,name', 'grade:id,name'])->loadCount('employees');
        });
    }

    /**
     * @throws ValidationException when employees or job openings still use the designation
     */
    public function delete(Designation $designation): void
    {
        if ($designation->employees()->exists() || JobOpening::where('designation_id', $designation->id)->exists()) {
            throw ValidationException::withMessages(['designation' => ['Employees or job openings still use this designation. Mark it inactive instead.']]);
        }

        DB::transaction(fn () => $designation->delete());
    }
}
