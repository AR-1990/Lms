<?php

namespace App\Services;

use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SectionService
{
    /**
     * Sections joined to their class so lists sort by grade level, then section name.
     *
     * @param  array{academic_session_id?: int|null, class_id?: int|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Section::query()
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->select([
                'sections.id', 'sections.class_id', 'sections.academic_session_id', 'sections.name', 'sections.room',
                'sections.capacity', 'sections.created_at', 'sections.updated_at', 'classes.name as class_name', 'classes.level_order',
            ])
            ->with('academicSession:id,name')
            ->when($filters['academic_session_id'] ?? null, fn (Builder $query, int $sessionId) => $query->where('sections.academic_session_id', $sessionId))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $classId) => $query->where('sections.class_id', $classId));
    }

    /**
     * @param  array{academic_session_id?: int|null, class_id?: int|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('classes.level_order')->orderBy('sections.name')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{academic_session_id?: int|null, class_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('session_name', fn (Section $section): ?string => $section->academicSession?->name)
            ->removeColumn('academic_session')
            ->filterColumn('class_name', fn (Builder $query, string $keyword) => $query->where('classes.name', 'like', "%{$keyword}%"))
            ->orderColumn('class_name', 'classes.level_order $1, sections.name $1')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Section
    {
        return DB::transaction(fn (): Section => $this->find(Section::create($data)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Section $section, array $data): Section
    {
        return DB::transaction(function () use ($section, $data): Section {
            $section->update($data);

            return $this->find($section);
        });
    }

    /**
     * Students and timetables will block this once those modules exist (PLAN.md phases 4 and 8).
     */
    public function delete(Section $section): void
    {
        DB::transaction(fn () => $section->delete());
    }

    public function find(Section $section): Section
    {
        return $this->query()->whereKey($section->id)->firstOrFail();
    }
}
