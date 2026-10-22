<?php

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Weekly timetables: one cell per section, day and period; a teacher is never in two places at once.
 */
class TimetableService
{
    /**
     * @return Collection<int, TimetableSlot>
     */
    public function forSection(Section $section): Collection
    {
        return TimetableSlot::where('section_id', $section->id)
            ->with(['subject:id,name,code', 'teacher:id,first_name,last_name,employee_code'])
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * A teacher's week across all their sections (teacher portal and mobile app).
     *
     * @return Collection<int, TimetableSlot>
     */
    public function forTeacher(Employee $teacher): Collection
    {
        return TimetableSlot::where('teacher_id', $teacher->id)
            ->with(['subject:id,name,code', 'section:id,name,class_id', 'section.schoolClass:id,name', 'period:id,name,starts_at,ends_at'])
            ->orderBy('day_of_week')
            ->get();
    }

    /**
     * Create or replace the cell for a section / day / period.
     *
     * @param  array{section_id: int, period_id: int, day_of_week: int, subject_id?: int|null, teacher_id?: int|null, room?: string|null}  $data
     *
     * @throws ValidationException when the subject is not taught in the class or the teacher is busy
     */
    public function save(array $data): TimetableSlot
    {
        $section = Section::findOrFail($data['section_id']);

        if (! empty($data['subject_id']) && ! ClassSubject::where('academic_session_id', $section->academic_session_id)->where('class_id', $section->class_id)->where('subject_id', $data['subject_id'])->exists()) {
            throw ValidationException::withMessages(['subject_id' => ['This subject is not in the class curriculum.']]);
        }

        if (! empty($data['teacher_id'])) {
            $clash = TimetableSlot::where('teacher_id', $data['teacher_id'])
                ->where('day_of_week', $data['day_of_week'])
                ->where('period_id', $data['period_id'])
                ->where('section_id', '!=', $section->id)
                ->with('section.schoolClass:id,name')
                ->first();

            if ($clash !== null) {
                throw ValidationException::withMessages(['teacher_id' => ["This teacher already takes {$clash->section->schoolClass->name} {$clash->section->name} in this period."]]);
            }
        }

        return DB::transaction(function () use ($data): TimetableSlot {
            $slot = TimetableSlot::firstOrNew(['section_id' => $data['section_id'], 'day_of_week' => $data['day_of_week'], 'period_id' => $data['period_id']]);
            $slot->fill($data)->save();

            return $slot->load(['subject:id,name,code', 'teacher:id,first_name,last_name,employee_code']);
        });
    }

    public function delete(TimetableSlot $slot): void
    {
        DB::transaction(fn () => $slot->delete());
    }
}
