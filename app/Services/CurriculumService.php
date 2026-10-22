<?php

namespace App\Services;

use App\Models\ClassSubject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class CurriculumService
{
    /**
     * Curriculum rows joined to class and subject so lists sort by grade, then report-card order.
     *
     * @param  array{academic_session_id?: int|null, class_id?: int|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return ClassSubject::query()
            ->join('classes', 'classes.id', '=', 'class_subject.class_id')
            ->join('subjects', 'subjects.id', '=', 'class_subject.subject_id')
            ->select([
                'class_subject.*', 'classes.name as class_name', 'classes.level_order',
                'subjects.name as subject_name', 'subjects.code as subject_code', 'subjects.has_practical',
            ])
            ->when($filters['academic_session_id'] ?? null, fn (Builder $query, int $sessionId) => $query->where('class_subject.academic_session_id', $sessionId))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('class_subject.class_id', $classId));
    }

    /**
     * @param  array{academic_session_id?: int|null, class_id?: int|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderBy('classes.level_order')
            ->orderBy('class_subject.sort_order')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{academic_session_id?: int|null, class_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->filterColumn('class_name', fn (Builder $query, string $keyword) => $query->where('classes.name', 'like', "%{$keyword}%"))
            ->filterColumn('subject_name', fn (Builder $query, string $keyword) => $query->where('subjects.name', 'like', "%{$keyword}%"))
            ->orderColumn('class_name', 'classes.level_order $1, class_subject.sort_order $1')
            ->orderColumn('subject_name', 'subjects.name $1')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClassSubject
    {
        return DB::transaction(fn (): ClassSubject => $this->find(ClassSubject::create([
            ...$data,
            'is_optional' => $data['is_optional'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
        ])));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClassSubject $classSubject, array $data): ClassSubject
    {
        return DB::transaction(function () use ($classSubject, $data): ClassSubject {
            $classSubject->update($data);

            return $this->find($classSubject);
        });
    }

    /**
     * Exams will block this once marks exist (PLAN.md phase 10).
     */
    public function delete(ClassSubject $classSubject): void
    {
        DB::transaction(fn () => $classSubject->delete());
    }

    /**
     * Copy one session's curriculum into another, skipping subjects the target already has.
     *
     * @return int number of rows copied
     *
     * @throws ValidationException when copying a session onto itself
     */
    public function copy(int $fromSessionId, int $toSessionId): int
    {
        if ($fromSessionId === $toSessionId) {
            throw ValidationException::withMessages(['to_session_id' => ['Choose a different session to copy into.']]);
        }

        return DB::transaction(function () use ($fromSessionId, $toSessionId): int {
            $existing = ClassSubject::where('academic_session_id', $toSessionId)
                ->get(['class_id', 'subject_id'])
                ->map(fn (ClassSubject $row): string => $row->class_id.'-'.$row->subject_id)
                ->flip();

            return ClassSubject::where('academic_session_id', $fromSessionId)->get()
                ->reject(fn (ClassSubject $row): bool => $existing->has($row->class_id.'-'.$row->subject_id))
                ->each(fn (ClassSubject $row) => ClassSubject::create([
                    ...$row->only(['class_id', 'subject_id', 'is_optional', 'periods_per_week', 'theory_max_marks', 'practical_max_marks', 'pass_marks', 'sort_order']),
                    'academic_session_id' => $toSessionId,
                ]))
                ->count();
        });
    }

    public function find(ClassSubject $classSubject): ClassSubject
    {
        return $this->query()->where('class_subject.id', $classSubject->id)->firstOrFail();
    }
}
