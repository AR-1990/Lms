<?php

namespace App\Services;

use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SubjectService
{
    /**
     * @param  array{is_active?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Subject::query()
            ->select(['id', 'name', 'code', 'subject_type_id', 'has_practical', 'is_active', 'created_at', 'updated_at'])
            ->with('subjectType:id,name')
            ->withCount('curriculum')
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }

    /**
     * @param  array{is_active?: string|null}  $filters
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
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('subject_type_name', fn (Subject $subject): ?string => $subject->subjectType?->name)
            ->removeColumn('subject_type')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Subject
    {
        return DB::transaction(fn (): Subject => Subject::create([
            ...$data,
            'has_practical' => $data['has_practical'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ])->load('subjectType:id,name')->loadCount('curriculum'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Subject $subject, array $data): Subject
    {
        return DB::transaction(function () use ($subject, $data): Subject {
            $subject->update($data);

            return $subject->load('subjectType:id,name')->loadCount('curriculum');
        });
    }

    /**
     * @throws ValidationException when a class curriculum still teaches the subject
     */
    public function delete(Subject $subject): void
    {
        if ($subject->curriculum()->exists()) {
            throw ValidationException::withMessages(['subject' => ['This subject is part of a class curriculum. Remove it there first, or mark it inactive.']]);
        }

        DB::transaction(fn () => $subject->delete());
    }
}
