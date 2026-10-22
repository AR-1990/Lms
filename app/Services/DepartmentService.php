<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class DepartmentService
{
    /**
     * @param  array{is_active?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Department::query()
            ->select(['id', 'name', 'code', 'head_employee_id', 'parent_id', 'is_active', 'created_at', 'updated_at'])
            ->with('head:id,first_name,last_name,employee_code')
            ->withCount(['employees', 'designations'])
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }

    /**
     * @param  array{is_active?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('head_name', fn (Department $department): ?string => $department->head?->fullName())
            ->removeColumn('head')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department
    {
        return DB::transaction(fn (): Department => Department::create([...$data, 'is_active' => $data['is_active'] ?? true])
            ->load('head:id,first_name,last_name,employee_code')
            ->loadCount(['employees', 'designations']));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data): Department {
            $department->update($data);

            return $department->load('head:id,first_name,last_name,employee_code')->loadCount(['employees', 'designations']);
        });
    }

    /**
     * @throws ValidationException when employees or designations still belong to the department
     */
    public function delete(Department $department): void
    {
        if ($department->employees()->exists() || $department->designations()->exists()) {
            throw ValidationException::withMessages(['department' => ['Move this department\'s employees and designations first, or mark it inactive.']]);
        }

        DB::transaction(fn () => $department->delete());
    }
}
