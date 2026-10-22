<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\MarkChangeLog;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks entry per paper and section: the subject teacher saves drafts and submits, the exam cell
 * approves. Any change after submission needs a reason and is kept in mark_change_logs.
 */
class MarksService
{
    /**
     * Exam statuses in which marks may be entered (review = corrections only).
     */
    public const OPEN_STATUSES = ['scheduled', 'admit_cards_issued', 'marks_entry', 'review', 'published'];

    /**
     * @throws ValidationException when the user neither manages exams nor teaches this subject in the section
     */
    public function ensureCanEnter(User $user, ExamSubject $paper, Section $section): void
    {
        $paper->loadMissing('exam');

        if ($section->class_id !== $paper->exam->class_id) {
            throw ValidationException::withMessages(['section_id' => ['This section is not in the exam\'s class.']]);
        }

        if ($user->hasPermission('manage-exams')) {
            return;
        }

        $employeeId = Employee::where('user_id', $user->id)->value('id');
        $teaches = $employeeId !== null && TeacherAssignment::where('employee_id', $employeeId)->where('section_id', $section->id)->where('subject_id', $paper->subject_id)->exists();

        if (! $teaches) {
            throw ValidationException::withMessages(['section_id' => ['Only the subject teacher of this section can enter these marks.']]);
        }
    }

    /**
     * @return array{paper: array<string, mixed>, rows: array<int, array<string, mixed>>}
     */
    public function sheet(ExamSubject $paper, Section $section): array
    {
        $paper->loadMissing(['exam:id,name,status', 'subject:id,name']);
        $marks = Mark::where('exam_subject_id', $paper->id)->where('section_id', $section->id)->get()->keyBy('enrollment_id');

        return [
            'paper' => [
                'id' => $paper->id,
                'exam' => $paper->exam->name,
                'exam_status' => $paper->exam->status,
                'subject' => $paper->subject->name,
                'theory_max_marks' => $paper->theory_max_marks,
                'practical_max_marks' => $paper->practical_max_marks,
                'pass_marks' => $paper->pass_marks,
            ],
            'rows' => Enrollment::where('section_id', $section->id)->where('status', 'active')->with('student:id,first_name,last_name,admission_no')->orderBy('roll_no')->get(['id', 'student_id', 'roll_no'])
                ->map(fn (Enrollment $enrollment): array => [
                    'enrollment_id' => $enrollment->id,
                    'name' => $enrollment->student->fullName(),
                    'admission_no' => $enrollment->student->admission_no,
                    'roll_no' => $enrollment->roll_no,
                    'theory_marks' => $marks->get($enrollment->id)?->theory_marks,
                    'practical_marks' => $marks->get($enrollment->id)?->practical_marks,
                    'is_absent' => (bool) $marks->get($enrollment->id)?->is_absent,
                    'grade' => $marks->get($enrollment->id)?->grade,
                    'teacher_remarks' => $marks->get($enrollment->id)?->teacher_remarks,
                    'status' => $marks->get($enrollment->id)?->status,
                ])->all(),
        ];
    }

    /**
     * Save a section's marks for one paper; `submit` sends them for approval.
     *
     * @param  array<int, array{enrollment_id: int, theory_marks?: float|null, practical_marks?: float|null, is_absent?: bool, teacher_remarks?: string|null}>  $rows
     *
     * @throws ValidationException for closed exams, marks above the maximum, or changes without a reason
     */
    public function save(ExamSubject $paper, Section $section, array $rows, bool $submit, ?string $reason, User $user): array
    {
        $this->ensureCanEnter($user, $paper, $section);
        $paper->loadMissing('exam.gradingScale.items');

        if (! in_array($paper->exam->status, self::OPEN_STATUSES, true)) {
            throw ValidationException::withMessages(['exam' => ['Marks entry is not open for this exam yet.']]);
        }

        $enrollments = Enrollment::where('section_id', $section->id)->pluck('id')->all();
        $existing = Mark::where('exam_subject_id', $paper->id)->whereIn('enrollment_id', array_column($rows, 'enrollment_id'))->get()->keyBy('enrollment_id');

        foreach ($rows as $index => $row) {
            if (! in_array($row['enrollment_id'], $enrollments, true)) {
                throw ValidationException::withMessages(["rows.{$index}.enrollment_id" => ['This student is not in the section.']]);
            }

            if (($row['theory_marks'] ?? 0) > $paper->theory_max_marks || ($row['practical_marks'] ?? 0) > $paper->practical_max_marks) {
                throw ValidationException::withMessages(["rows.{$index}.theory_marks" => ["Marks cannot be more than the maximum ({$paper->theory_max_marks} theory, {$paper->practical_max_marks} practical)."]]);
            }

            $mark = $existing->get($row['enrollment_id']);

            if ($mark !== null && $mark->status !== 'draft' && $this->changed($mark, $row)) {
                if (blank($reason)) {
                    throw ValidationException::withMessages(['reason' => ['These marks were already submitted; give a reason for the change.']]);
                }

                if ($mark->status === 'approved' && ! $user->hasPermission('manage-exams')) {
                    throw ValidationException::withMessages(['reason' => ['Approved marks can only be changed by the exam cell.']]);
                }
            }
        }

        DB::transaction(function () use ($paper, $section, $rows, $submit, $reason, $user, $existing): void {
            foreach ($rows as $row) {
                $mark = $existing->get($row['enrollment_id']) ?? new Mark(['exam_subject_id' => $paper->id, 'enrollment_id' => $row['enrollment_id'], 'section_id' => $section->id, 'status' => 'draft']);
                $absent = (bool) ($row['is_absent'] ?? false);

                if ($mark->exists && $mark->status !== 'draft' && $this->changed($mark, $row)) {
                    MarkChangeLog::create([
                        'mark_id' => $mark->id,
                        'old_theory' => $mark->theory_marks, 'new_theory' => $row['theory_marks'] ?? null,
                        'old_practical' => $mark->practical_marks, 'new_practical' => $row['practical_marks'] ?? null,
                        'old_absent' => $mark->is_absent, 'new_absent' => $absent,
                        'reason' => $reason, 'changed_by' => $user->id, 'created_at' => now(),
                    ]);
                }

                $mark->fill([
                    'theory_marks' => $absent ? null : ($row['theory_marks'] ?? null),
                    'practical_marks' => $absent ? null : ($row['practical_marks'] ?? null),
                    'is_absent' => $absent,
                    'teacher_remarks' => $row['teacher_remarks'] ?? null,
                    'entered_by' => $user->id,
                ]);
                $mark->grade = $paper->exam->gradingScale?->gradeFor($paper->maxMarks() ? $mark->obtained() * 100 / $paper->maxMarks() : 0)?->grade;

                if ($submit && $mark->status === 'draft') {
                    $mark->status = 'submitted';
                }

                if ($mark->isDirty()) {
                    $mark->save();
                }
            }
        });

        return $this->sheet($paper, $section);
    }

    /**
     * @return array{approved: int}
     *
     * @throws ValidationException when some students have no submitted marks
     */
    public function approve(ExamSubject $paper, Section $section, User $user): array
    {
        $students = Enrollment::where('section_id', $section->id)->where('status', 'active')->count();
        $ready = Mark::where('exam_subject_id', $paper->id)->where('section_id', $section->id)->whereIn('status', ['submitted', 'approved'])->count();

        if ($ready < $students) {
            throw ValidationException::withMessages(['marks' => [($students - $ready).' student(s) still have no submitted marks.']]);
        }

        $approved = DB::transaction(fn (): int => Mark::where('exam_subject_id', $paper->id)->where('section_id', $section->id)->where('status', 'submitted')->get()
            ->each(fn (Mark $mark) => $mark->update(['status' => 'approved']))->count());

        return ['approved' => $approved];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function changed(Mark $mark, array $row): bool
    {
        return (float) $mark->theory_marks !== (float) ($row['theory_marks'] ?? 0)
            || (float) $mark->practical_marks !== (float) ($row['practical_marks'] ?? 0)
            || $mark->is_absent !== (bool) ($row['is_absent'] ?? false);
    }
}
