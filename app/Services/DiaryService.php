<?php

namespace App\Services;

use App\Models\Diary;
use App\Models\DiaryAcknowledgement;
use App\Models\DiaryEntry;
use App\Models\DiaryStudentRemark;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\Lookup;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The digital daily diary: teachers add entries to a section's page for the day, the class teacher
 * publishes it, parents read and "sign" it. A homework entry can also create a gradable homework.
 */
class DiaryService
{
    public function __construct(private TeacherAssignmentService $assignmentService) {}

    /**
     * @throws ValidationException when the user does not teach the section
     */
    public function ensureCanWrite(User $user, Section $section): void
    {
        if (! $user->hasPermission('manage-students') && ! $this->assignmentService->teaches($user, $section)) {
            throw ValidationException::withMessages(['section_id' => ['You can write the diary only for sections you teach.']]);
        }
    }

    /**
     * The page for a section and day (null when nothing was written yet), with who has not signed.
     *
     * @return array{diary: Diary|null, students: array<int, array{student_id: int, name: string}>, unsigned: array<int, array{student_id: int, name: string}>}
     */
    public function page(Section $section, string $date): array
    {
        $diary = Diary::where('section_id', $section->id)->whereDate('diary_date', $date)
            ->with(['entries.subject:id,name', 'entries.teacher:id,first_name,last_name', 'entries.entryType:id,name', 'remarks.student:id,first_name,last_name', 'remarks.remarkType:id,name'])
            ->first();

        $students = Enrollment::where('section_id', $section->id)->where('status', 'active')->with('student:id,first_name,last_name')->orderBy('roll_no')->get(['student_id', 'roll_no'])
            ->map(fn (Enrollment $enrollment): array => ['student_id' => $enrollment->student_id, 'name' => $enrollment->student->fullName()]);
        $signed = $diary?->status === 'published' ? DiaryAcknowledgement::where('diary_id', $diary->id)->pluck('student_id')->all() : null;

        return [
            'diary' => $diary,
            'students' => $students->all(),
            'unsigned' => $signed === null ? [] : $students->reject(fn (array $student): bool => in_array($student['student_id'], $signed, true))->values()->all(),
        ];
    }

    /**
     * Add a line to the day's page (creating the page as a draft). `create_homework` also makes a homework record.
     *
     * @param  array<string, mixed>  $data  section_id, diary_date, entry_type_id, subject_id, title, details, due_on, create_homework, max_marks, allow_submission
     */
    public function addEntry(array $data, User $user): DiaryEntry
    {
        $section = Section::findOrFail($data['section_id']);
        $this->ensureCanWrite($user, $section);

        return DB::transaction(function () use ($data, $section, $user): DiaryEntry {
            $diary = $this->diaryFor($section, $data['diary_date']);
            $teacherId = Employee::where('user_id', $user->id)->value('id');
            $homeworkId = null;

            if (! empty($data['create_homework']) && ! empty($data['subject_id'])) {
                $homeworkId = Homework::create([
                    'section_id' => $section->id,
                    'subject_id' => $data['subject_id'],
                    'teacher_id' => $teacherId,
                    'title' => $data['title'] ?? 'Homework',
                    'description' => $data['details'],
                    'assigned_on' => $data['diary_date'],
                    'due_on' => $data['due_on'] ?? null,
                    'max_marks' => $data['max_marks'] ?? null,
                    'allow_submission' => (bool) ($data['allow_submission'] ?? false),
                ])->id;
            }

            return $diary->entries()->create([
                'subject_id' => $data['subject_id'] ?? null,
                'teacher_id' => $teacherId,
                'entry_type_id' => $data['entry_type_id'],
                'title' => $data['title'] ?? null,
                'details' => $data['details'],
                'homework_id' => $homeworkId,
                'due_on' => $data['due_on'] ?? null,
            ])->load(['subject:id,name', 'entryType:id,name', 'teacher:id,first_name,last_name']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEntry(DiaryEntry $entry, array $data, User $user): DiaryEntry
    {
        $this->ensureCanWrite($user, $entry->diary->section);

        return DB::transaction(function () use ($entry, $data): DiaryEntry {
            $entry->update(collect($data)->only(['subject_id', 'entry_type_id', 'title', 'details', 'due_on'])->all());

            return $entry->load(['subject:id,name', 'entryType:id,name', 'teacher:id,first_name,last_name']);
        });
    }

    public function deleteEntry(DiaryEntry $entry): void
    {
        DB::transaction(fn () => $entry->delete());
    }

    public function saveNote(Section $section, string $date, ?string $note, User $user): Diary
    {
        $this->ensureCanWrite($user, $section);

        return DB::transaction(function () use ($section, $date, $note): Diary {
            $diary = $this->diaryFor($section, $date);
            $diary->update(['class_teacher_note' => $note]);

            return $diary;
        });
    }

    /**
     * @throws ValidationException when the page has nothing on it
     */
    public function publish(Diary $diary, User $user): Diary
    {
        $this->ensureCanWrite($user, $diary->section);

        if ($diary->entries()->doesntExist() && blank($diary->class_teacher_note)) {
            throw ValidationException::withMessages(['diary' => ['Add at least one entry or a note before publishing.']]);
        }

        $diary->update(['status' => 'published', 'published_at' => $diary->published_at ?? now(), 'published_by' => $diary->published_by ?? $user->id]);

        return $diary;
    }

    /**
     * @param  array{student_id: int, remark_type_id?: int|null, remark: string}  $data
     *
     * @throws ValidationException when the student is not in the section
     */
    public function addRemark(Diary $diary, array $data, User $user): DiaryStudentRemark
    {
        $this->ensureCanWrite($user, $diary->section);

        if (! Enrollment::where('section_id', $diary->section_id)->where('student_id', $data['student_id'])->exists()) {
            throw ValidationException::withMessages(['student_id' => ['This student is not in the section.']]);
        }

        return $diary->remarks()->create([...$data, 'teacher_id' => Employee::where('user_id', $user->id)->value('id')])->load(['student:id,first_name,last_name', 'remarkType:id,name']);
    }

    /**
     * A parent marks a published page as seen for their child.
     *
     * @throws ValidationException when the page is not published or the child is not theirs
     */
    public function acknowledge(Diary $diary, Student $student, User $user, ?string $comment, ?string $device): DiaryAcknowledgement
    {
        $guardian = Guardian::where('user_id', $user->id)->first();

        if ($diary->status !== 'published') {
            throw ValidationException::withMessages(['diary' => ['This diary page is not published yet.']]);
        }

        if ($guardian === null || ! in_array($student->id, Student::ownIdsFor($user), true) || ! Enrollment::where('section_id', $diary->section_id)->where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages(['student_id' => ['Only a parent of a student in this class can sign the diary.']]);
        }

        return DB::transaction(fn (): DiaryAcknowledgement => DiaryAcknowledgement::firstOrCreate(
            ['diary_id' => $diary->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id],
            ['acknowledged_at' => now(), 'parent_comment' => $comment, 'device' => $device],
        ));
    }

    /**
     * Published pages for one student (parent / student portal and app), with only that student's remarks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(Student $student, string $from, string $to): array
    {
        $sectionIds = Enrollment::where('student_id', $student->id)->pluck('section_id')->filter();

        return Diary::whereIn('section_id', $sectionIds)->where('status', 'published')->whereBetween('diary_date', [$from, $to])
            ->with(['entries.subject:id,name', 'entries.entryType:id,name', 'entries.teacher:id,first_name,last_name', 'remarks' => fn ($query) => $query->where('student_id', $student->id)->with('remarkType:id,name')])
            ->withExists(['acknowledgements as signed' => fn ($query) => $query->where('student_id', $student->id)])
            ->orderByDesc('diary_date')
            ->get()
            ->map(fn (Diary $diary): array => [
                'id' => $diary->id,
                'date' => $diary->diary_date->toDateString(),
                'note' => $diary->class_teacher_note,
                'signed' => (bool) $diary->signed,
                'entries' => $diary->entries->map(fn (DiaryEntry $entry): array => [
                    'type' => $entry->entryType?->name,
                    'subject' => $entry->subject?->name,
                    'teacher' => $entry->teacher?->fullName(),
                    'title' => $entry->title,
                    'details' => $entry->details,
                    'due_on' => $entry->due_on?->toDateString(),
                    'homework_id' => $entry->homework_id,
                ])->all(),
                'remarks' => $diary->remarks->map(fn (DiaryStudentRemark $remark): array => ['type' => $remark->remarkType?->name, 'remark' => $remark->remark])->all(),
            ])->all();
    }

    public function homeworkTypeId(): ?int
    {
        return Lookup::where('type', 'diary_entry_type')->where('name', 'Homework')->value('id');
    }

    private function diaryFor(Section $section, string $date): Diary
    {
        return Diary::where('section_id', $section->id)->whereDate('diary_date', $date)->first()
            ?? Diary::create(['section_id' => $section->id, 'diary_date' => $date, 'status' => 'draft']);
    }
}
