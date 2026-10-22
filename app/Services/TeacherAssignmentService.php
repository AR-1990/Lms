<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class TeacherAssignmentService
{
    /**
     * @param  array{academic_session_id?: int|null, section_id?: int|null, employee_id?: int|null}  $filters
     */
    /**
     * Whether the login belongs to the section's class teacher or a teacher assigned to it.
     */
    public function teaches(User $user, Section $section): bool
    {
        $employeeId = Employee::where('user_id', $user->id)->value('id');

        return $employeeId !== null && ($section->class_teacher_id === $employeeId
            || TeacherAssignment::where('employee_id', $employeeId)->where('section_id', $section->id)->exists());
    }

    public function query(array $filters = []): Builder
    {
        return TeacherAssignment::query()
            ->join('sections', 'sections.id', '=', 'teacher_assignments.section_id')
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->join('subjects', 'subjects.id', '=', 'teacher_assignments.subject_id')
            ->join('employees', 'employees.id', '=', 'teacher_assignments.employee_id')
            ->select([
                'teacher_assignments.*', 'sections.name as section_name', 'classes.name as class_name', 'classes.level_order',
                'subjects.name as subject_name', 'employees.first_name', 'employees.last_name', 'employees.employee_code',
            ])
            ->when($filters['academic_session_id'] ?? null, fn (Builder $query, int $id) => $query->where('teacher_assignments.academic_session_id', $id))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('teacher_assignments.section_id', $id))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int $id) => $query->where('teacher_assignments.employee_id', $id));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('teacher_name', fn (TeacherAssignment $row): string => trim($row->first_name.' '.$row->last_name))
            ->filterColumn('class_name', fn (Builder $query, string $keyword) => $query->where('classes.name', 'like', "%{$keyword}%"))
            ->filterColumn('subject_name', fn (Builder $query, string $keyword) => $query->where('subjects.name', 'like', "%{$keyword}%"))
            ->filterColumn('teacher_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('employees.first_name', 'like', "%{$keyword}%")
                ->orWhere('employees.last_name', 'like', "%{$keyword}%")))
            ->orderColumn('class_name', 'classes.level_order $1, sections.name $1')
            ->orderColumn('subject_name', 'subjects.name $1')
            ->orderColumn('teacher_name', 'employees.first_name $1')
            ->toArray();
    }

    public function find(TeacherAssignment $assignment): TeacherAssignment
    {
        return $this->query()->where('teacher_assignments.id', $assignment->id)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data): TeacherAssignment
    {
        $this->check($data);

        return DB::transaction(function () use ($data): TeacherAssignment {
            $assignment = TeacherAssignment::create([...$data, 'is_class_teacher' => $data['is_class_teacher'] ?? false]);
            $this->syncClassTeacher($assignment);

            return $this->find($assignment);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(TeacherAssignment $assignment, array $data): TeacherAssignment
    {
        $this->check($data);

        return DB::transaction(function () use ($assignment, $data): TeacherAssignment {
            $wasClassTeacher = $assignment->is_class_teacher;
            $assignment->update($data);

            if ($wasClassTeacher && ! $assignment->is_class_teacher) {
                Section::whereKey($assignment->section_id)->where('class_teacher_id', $assignment->employee_id)->update(['class_teacher_id' => null]);
            }

            $this->syncClassTeacher($assignment);

            return $this->find($assignment);
        });
    }

    public function delete(TeacherAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            if ($assignment->is_class_teacher) {
                Section::whereKey($assignment->section_id)->where('class_teacher_id', $assignment->employee_id)->update(['class_teacher_id' => null]);
            }

            $assignment->delete();
        });
    }

    /**
     * The section must be in the session, the subject in that class's curriculum, and the teacher still working.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function check(array $data): void
    {
        $section = Section::find($data['section_id']);

        if ($section === null || $section->academic_session_id !== (int) $data['academic_session_id']) {
            throw ValidationException::withMessages(['section_id' => ['This section does not belong to the chosen session.']]);
        }

        $inCurriculum = ClassSubject::where('academic_session_id', $section->academic_session_id)
            ->where('class_id', $section->class_id)
            ->where('subject_id', $data['subject_id'])
            ->exists();

        if (! $inCurriculum) {
            throw ValidationException::withMessages(['subject_id' => ['This subject is not in the class curriculum for the session. Add it on the Curriculum tab first.']]);
        }

        if (! Employee::whereKey($data['employee_id'])->whereIn('status', Employee::CURRENT_STATUSES)->exists()) {
            throw ValidationException::withMessages(['employee_id' => ['Choose a teacher who currently works at the school.']]);
        }
    }

    /**
     * One class teacher per section: the section points at them and other assignments lose the flag.
     */
    private function syncClassTeacher(TeacherAssignment $assignment): void
    {
        if (! $assignment->is_class_teacher) {
            return;
        }

        TeacherAssignment::where('section_id', $assignment->section_id)
            ->where('is_class_teacher', true)
            ->whereKeyNot($assignment->id)
            ->get()
            ->each(fn (TeacherAssignment $other) => $other->update(['is_class_teacher' => false]));

        Section::whereKey($assignment->section_id)->update(['class_teacher_id' => $assignment->employee_id]);
    }
}
