<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Homework set for a section, online submissions by students / parents, and grading.
 */
class HomeworkService
{
    public function __construct(private DiaryService $diaryService) {}

    /**
     * @param  array{section_id?: int|null, subject_id?: int|null, section_ids?: array<int, int>}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Homework::query()
            ->with(['section:id,name,class_id', 'section.schoolClass:id,name', 'subject:id,name', 'teacher:id,first_name,last_name'])
            ->withCount(['submissions as submitted_count' => fn (Builder $query) => $query->whereIn('status', ['submitted', 'late', 'graded'])])
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('section_id', $id))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int $id) => $query->where('subject_id', $id))
            ->when(array_key_exists('section_ids', $filters), fn (Builder $query) => $query->whereIn('section_id', $filters['section_ids']));
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('section_name', fn (Homework $homework): string => trim(($homework->section?->schoolClass?->name ?? '').' '.($homework->section?->name ?? '')))
            ->addColumn('subject_name', fn (Homework $homework): ?string => $homework->subject?->name)
            ->addColumn('teacher_name', fn (Homework $homework): ?string => $homework->teacher?->fullName())
            ->editColumn('assigned_on', fn (Homework $homework): string => $homework->assigned_on->toDateString())
            ->editColumn('due_on', fn (Homework $homework): ?string => $homework->due_on?->toDateString())
            ->removeColumn('section', 'subject', 'teacher')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Homework $homework, array $data, User $user): Homework
    {
        $this->diaryService->ensureCanWrite($user, Section::findOrFail($data['section_id'] ?? $homework->section_id));

        return DB::transaction(function () use ($homework, $data, $user): Homework {
            if ($homework === null) {
                $homework = Homework::create([...$data, 'teacher_id' => Employee::where('user_id', $user->id)->value('id')]);
            } else {
                $homework->update($data);
            }

            return $homework->load(['section:id,name,class_id', 'section.schoolClass:id,name', 'subject:id,name', 'teacher:id,first_name,last_name']);
        });
    }

    public function delete(Homework $homework): void
    {
        DB::transaction(fn () => $homework->delete());
    }

    /**
     * Every student of the section with their submission (or "pending").
     *
     * @return array<int, array<string, mixed>>
     */
    public function submissions(Homework $homework): array
    {
        $submissions = HomeworkSubmission::where('homework_id', $homework->id)->get()->keyBy('student_id');

        return Enrollment::where('section_id', $homework->section_id)->where('status', 'active')->with('student:id,first_name,last_name,admission_no')->get(['student_id', 'roll_no'])
            ->sortBy('roll_no')
            ->map(function (Enrollment $enrollment) use ($submissions): array {
                $submission = $submissions->get($enrollment->student_id);

                return [
                    'student_id' => $enrollment->student_id,
                    'name' => $enrollment->student->fullName(),
                    'admission_no' => $enrollment->student->admission_no,
                    'submission_id' => $submission?->id,
                    'status' => $submission?->status ?? 'pending',
                    'submitted_at' => $submission?->submitted_at?->toIso8601String(),
                    'content' => $submission?->content,
                    'marks' => $submission?->marks,
                    'feedback' => $submission?->feedback,
                ];
            })->values()->all();
    }

    /**
     * A student (or their parent) hands in work; after the due date it is marked late.
     *
     * @throws ValidationException when online submission is off, the student is not in the section, or it was graded
     */
    public function submit(Homework $homework, Student $student, ?string $content, User $user): HomeworkSubmission
    {
        if (! $homework->allow_submission) {
            throw ValidationException::withMessages(['homework' => ['This homework is not submitted online.']]);
        }

        if (! in_array($student->id, Student::ownIdsFor($user), true) || ! Enrollment::where('section_id', $homework->section_id)->where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages(['student_id' => ['You can submit only for your own child in this class.']]);
        }

        $existing = HomeworkSubmission::where('homework_id', $homework->id)->where('student_id', $student->id)->first();

        if ($existing?->status === 'graded') {
            throw ValidationException::withMessages(['homework' => ['This work has already been graded.']]);
        }

        return DB::transaction(fn (): HomeworkSubmission => HomeworkSubmission::updateOrCreate(
            ['homework_id' => $homework->id, 'student_id' => $student->id],
            ['content' => $content, 'submitted_at' => now(), 'status' => $homework->due_on !== null && now()->startOfDay()->gt($homework->due_on) ? 'late' : 'submitted'],
        ));
    }

    /**
     * Grade one student's work (also for paper submissions: the teacher grades without an online copy).
     *
     * @param  array{student_id: int, marks?: float|null, feedback?: string|null}  $data
     *
     * @throws ValidationException when marks exceed the maximum
     */
    public function grade(Homework $homework, array $data, User $user): HomeworkSubmission
    {
        $this->diaryService->ensureCanWrite($user, $homework->section);

        if ($homework->max_marks !== null && isset($data['marks']) && $data['marks'] > $homework->max_marks) {
            throw ValidationException::withMessages(['marks' => ["Marks cannot be more than {$homework->max_marks}."]]);
        }

        return DB::transaction(fn (): HomeworkSubmission => HomeworkSubmission::updateOrCreate(
            ['homework_id' => $homework->id, 'student_id' => $data['student_id']],
            ['marks' => $data['marks'] ?? null, 'feedback' => $data['feedback'] ?? null, 'status' => 'graded', 'graded_by' => $user->id, 'graded_at' => now()],
        ));
    }
}
