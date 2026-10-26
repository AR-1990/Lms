<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\OnlineTest;
use App\Models\OnlineTestAttempt;
use App\Models\OnlineTestQuestion;
use App\Models\Section;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Online MCQ tests: the subject teacher builds and publishes a test for a section; students attempt it
 * once inside its window and are marked on submit. A test linked to an exam paper writes each score
 * into marks (scaled to the paper's theory marks) as submitted marks, so the exam cell approves them
 * as usual. Manual papers keep normal marks entry.
 */
class OnlineTestService
{
    /**
     * Minutes allowed after the timer for a slow connection to deliver the answers.
     */
    private const GRACE_MINUTES = 2;

    public function __construct(
        private AcademicSessionService $sessionService,
        private ActivityLogger $activityLogger,
        private NotificationService $notificationService,
    ) {}

    /**
     * Tests the user may manage: all for the exam cell, otherwise those of sections and subjects they teach.
     */
    public function query(User $user): Builder
    {
        $query = OnlineTest::query()
            ->leftJoin('sections', 'sections.id', '=', 'online_tests.section_id')
            ->leftJoin('classes', 'classes.id', '=', 'sections.class_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'online_tests.subject_id')
            ->select('online_tests.*', 'subjects.name as subject_name', 'classes.name as class_name', 'sections.name as section_name')
            ->withCount(['questions', 'attempts as submitted_count' => fn (Builder $attempts) => $attempts->where('status', 'submitted')]);

        if (! $user->hasPermission('manage-exams')) {
            $employeeId = Employee::where('user_id', $user->id)->value('id') ?? 0;
            $query->where(fn (Builder $own) => $own->where('online_tests.created_by', $user->id)
                ->orWhereExists(fn ($assigned) => $assigned->from('teacher_assignments')
                    ->whereColumn('teacher_assignments.section_id', 'online_tests.section_id')
                    ->whereColumn('teacher_assignments.subject_id', 'online_tests.subject_id')
                    ->where('teacher_assignments.employee_id', $employeeId)
                    ->whereNull('teacher_assignments.deleted_at')));
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(User $user): array
    {
        return DataTables::eloquent($this->query($user))
            ->filterColumn('class_name', fn (Builder $query, string $keyword) => $query->where('classes.name', 'like', "%{$keyword}%"))
            ->filterColumn('section_name', fn (Builder $query, string $keyword) => $query->where('sections.name', 'like', "%{$keyword}%"))
            ->filterColumn('subject_name', fn (Builder $query, string $keyword) => $query->where('subjects.name', 'like', "%{$keyword}%"))
            ->toArray();
    }

    public function find(OnlineTest $test, User $user): OnlineTest
    {
        $this->ensureTeaches($user, $test->section_id, $test->subject_id);

        return $test->load(['questions.options', 'section:id,name,class_id', 'subject:id,name', 'examSubject.exam:id,name']);
    }

    /**
     * Create or update a test with its questions. Once a student has submitted, only the title, window
     * and instructions can change (the questions are already marked).
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?OnlineTest $test, array $data, User $user): OnlineTest
    {
        $this->ensureTeaches($user, (int) $data['section_id'], (int) $data['subject_id']);
        $section = Section::findOrFail($data['section_id']);

        if (! empty($data['exam_subject_id'])) {
            $paper = ExamSubject::with('exam:id,class_id')->findOrFail($data['exam_subject_id']);

            if ($paper->subject_id !== (int) $data['subject_id'] || $paper->exam?->class_id !== $section->class_id) {
                throw ValidationException::withMessages(['exam_subject_id' => ['This exam paper is for another class or subject.']]);
            }
        }

        $marked = $test !== null && $test->attempts()->exists();

        if ($marked && (isset($data['questions']) || (int) $data['section_id'] !== $test->section_id || (int) $data['subject_id'] !== $test->subject_id)) {
            throw ValidationException::withMessages(['questions' => ['Students have already attempted this test; its questions, section and subject cannot change.']]);
        }

        foreach ($data['questions'] ?? [] as $index => $question) {
            if (collect($question['options'])->where('is_correct', true)->count() !== 1) {
                throw ValidationException::withMessages(["questions.{$index}.options" => ['Question '.($index + 1).': mark exactly one option as the correct answer.']]);
            }
        }

        return DB::transaction(function () use ($test, $data, $section, $marked): OnlineTest {
            $fields = collect($data)->only(['title', 'instructions', 'section_id', 'subject_id', 'exam_subject_id', 'opens_at', 'closes_at', 'duration_minutes', 'shuffle_questions', 'show_answers'])->all();
            $test ??= new OnlineTest(['status' => 'draft']);
            $test->fill([...$fields, 'academic_session_id' => $section->academic_session_id])->save();

            if (! $marked && isset($data['questions'])) {
                $test->questions()->get()->each(function (OnlineTestQuestion $question): void {
                    $question->options()->get()->each->delete();
                    $question->delete();
                });

                foreach (array_values($data['questions']) as $position => $row) {
                    $question = $test->questions()->create(['question' => $row['question'], 'marks' => $row['marks'] ?? 1, 'sort_order' => $position]);

                    foreach (array_values($row['options']) as $order => $option) {
                        $question->options()->create(['option_text' => $option['option_text'], 'is_correct' => (bool) ($option['is_correct'] ?? false), 'sort_order' => $order]);
                    }
                }

                $test->update(['total_marks' => (int) $test->questions()->sum('marks')]);
                $this->activityLogger->record('exams', 'questions_saved', count($data['questions']).' question(s) saved for online test '.$test->title, $test);
            }

            return $test->refresh()->load(['questions.options']);
        });
    }

    /**
     * Publish (students are told), close, or take back to draft while nobody has attempted it.
     */
    public function changeStatus(OnlineTest $test, string $status, User $user): OnlineTest
    {
        $this->ensureTeaches($user, $test->section_id, $test->subject_id);

        if ($status === 'published' && ! $test->questions()->exists()) {
            throw ValidationException::withMessages(['status' => ['Add at least one question before publishing.']]);
        }

        if ($status === 'draft' && $test->attempts()->exists()) {
            throw ValidationException::withMessages(['status' => ['Students have already attempted this test.']]);
        }

        DB::transaction(function () use ($test, $status): void {
            $test->update(['status' => $status]);

            if ($status === 'published') {
                $studentIds = Enrollment::where('section_id', $test->section_id)->pluck('student_id');
                $this->notificationService->notify(
                    $this->notificationService->familyUserIds($studentIds)->all(), 'online_test_published',
                    'New online test: '.$test->title,
                    $test->loadMissing('subject:id,name')->subject?->name.($test->closes_at ? ' · open until '.$test->closes_at->format('d M, h:i A') : ''),
                    route('student.online-tests'), $test,
                );
            }
        });

        return $test;
    }

    public function delete(OnlineTest $test): void
    {
        if ($test->attempts()->exists()) {
            throw ValidationException::withMessages(['online_test' => ['Students have already attempted this test; close it instead.']]);
        }

        DB::transaction(function () use ($test): void {
            $test->questions()->get()->each(function (OnlineTestQuestion $question): void {
                $question->options()->get()->each->delete();
                $question->delete();
            });
            $test->delete();
        });
    }

    /**
     * Every student of the section with their score (or that they have not attempted it yet).
     *
     * @return array{test: array<string, mixed>, rows: array<int, array<string, mixed>>}
     */
    public function results(OnlineTest $test, User $user): array
    {
        $this->ensureTeaches($user, $test->section_id, $test->subject_id);
        $attempts = $test->attempts()->get()->keyBy('student_id');
        $enrollments = Enrollment::where('section_id', $test->section_id)->with('student:id,first_name,middle_name,last_name,admission_no')->orderBy('roll_no')->get();

        return [
            'test' => ['id' => $test->id, 'title' => $test->title, 'total_marks' => $test->total_marks, 'status' => $test->status],
            'rows' => $enrollments->map(fn (Enrollment $enrollment): array => [
                'student_id' => $enrollment->student_id,
                'name' => $enrollment->student?->fullName(),
                'admission_no' => $enrollment->student?->admission_no,
                'roll_no' => $enrollment->roll_no,
                'status' => $attempts->get($enrollment->student_id)?->status ?? 'not_attempted',
                'score' => $attempts->get($enrollment->student_id)?->score,
                'correct_count' => $attempts->get($enrollment->student_id)?->correct_count,
                'submitted_at' => $attempts->get($enrollment->student_id)?->submitted_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /* ---------- Students (web portal and mobile app) ---------- */

    /**
     * Published tests of the student's current section, with their own attempt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(User $user, ?Student $child = null): array
    {
        if ($child !== null) {
            abort_unless($user->hasPermission(['manage-exams', 'manage-students']) || in_array($child->id, Student::ownIdsFor($user), true), 404, 'Student not found.');
        }

        $enrollment = $child !== null ? ($child->currentEnrollment ?? abort(404, 'Student not found.')) : $this->ownEnrollment($user);
        $tests = OnlineTest::where('section_id', $enrollment->section_id)->whereIn('status', ['published', 'closed'])
            ->with(['subject:id,name', 'attempts' => fn ($attempts) => $attempts->where('student_id', $enrollment->student_id)])
            ->latest('id')
            ->limit(100)
            ->get();

        return $tests->map(function (OnlineTest $test): array {
            $attempt = $test->attempts->first();

            return [
                'id' => $test->id,
                'title' => $test->title,
                'subject' => $test->subject?->name,
                'instructions' => $test->instructions,
                'opens_at' => $test->opens_at?->toIso8601String(),
                'closes_at' => $test->closes_at?->toIso8601String(),
                'duration_minutes' => $test->duration_minutes,
                'total_marks' => $test->total_marks,
                'is_open' => $test->isOpen(),
                'attempt_status' => $attempt?->status,
                'score' => $attempt?->status === 'submitted' ? $attempt->score : null,
            ];
        })->all();
    }

    /**
     * Start (or continue) the student's attempt: the questions without answers and the seconds left.
     *
     * @return array<string, mixed>
     */
    public function start(OnlineTest $test, User $user): array
    {
        $enrollment = $this->ownEnrollment($user, $test);
        $attempt = $test->attempts()->where('student_id', $enrollment->student_id)->first();

        if ($attempt?->status === 'submitted') {
            throw ValidationException::withMessages(['online_test' => ['You have already submitted this test.']]);
        }

        if (! $test->isOpen()) {
            throw ValidationException::withMessages(['online_test' => ['This test is not open right now.']]);
        }

        $attempt ??= DB::transaction(fn (): OnlineTestAttempt => $test->attempts()->create(['student_id' => $enrollment->student_id, 'enrollment_id' => $enrollment->id, 'started_at' => now(), 'status' => 'in_progress']));
        $questions = $test->questions()->with('options:id,online_test_question_id,option_text,sort_order')->get(['id', 'online_test_id', 'question', 'marks', 'sort_order']);
        // The same student always sees the same shuffled order, even after a refresh.
        $questions = $test->shuffle_questions ? $questions->sortBy(fn (OnlineTestQuestion $question): string => md5($attempt->id.'-'.$question->id))->values() : $questions;

        return [
            'attempt_id' => $attempt->id,
            'title' => $test->title,
            'instructions' => $test->instructions,
            'total_marks' => $test->total_marks,
            'seconds_left' => max(0, (int) now()->diffInSeconds($this->deadline($test, $attempt), false)),
            'saved' => $attempt->answers()->whereNotNull('online_test_option_id')->pluck('online_test_option_id', 'online_test_question_id')->all(),
            'questions' => $questions->map(fn (OnlineTestQuestion $question): array => [
                'id' => $question->id,
                'question' => $question->question,
                'marks' => $question->marks,
                'options' => $question->options->map(fn ($option): array => ['id' => $option->id, 'option_text' => $option->option_text])->values()->all(),
            ])->all(),
        ];
    }

    /**
     * Submit and mark the attempt. Answers arriving after the time limit (plus a short grace) are not counted.
     *
     * @param  array<int, array{question_id: int, option_id?: int|null}>  $answers
     * @return array<string, mixed>
     */
    public function submit(OnlineTest $test, User $user, array $answers): array
    {
        $enrollment = $this->ownEnrollment($user, $test);
        $attempt = $test->attempts()->where('student_id', $enrollment->student_id)->first();

        if ($attempt === null || $attempt->status === 'submitted') {
            throw ValidationException::withMessages(['online_test' => [$attempt === null ? 'Start the test first.' : 'You have already submitted this test.']]);
        }

        $late = now()->gt($this->deadline($test, $attempt)->addMinutes(self::GRACE_MINUTES));
        $this->finish($test, $attempt, $late ? [] : collect($answers)->pluck('option_id', 'question_id')->all());

        return [...$this->review($test, $attempt->refresh()), 'late' => $late];
    }

    /**
     * Save answers while the test is running (each click), so a dropped connection or a closed tab loses
     * nothing: the student continues on any device, and an attempt left open is marked when its time ends.
     *
     * @param  array<int, array{question_id: int, option_id?: int|null}>  $answers
     */
    public function saveAnswers(OnlineTest $test, User $user, array $answers): int
    {
        $enrollment = $this->ownEnrollment($user, $test);
        $attempt = $test->attempts()->where('student_id', $enrollment->student_id)->where('status', 'in_progress')->first();

        if ($attempt === null || now()->gt($this->deadline($test, $attempt)->addMinutes(self::GRACE_MINUTES))) {
            throw ValidationException::withMessages(['online_test' => ['This test is no longer running.']]);
        }

        $options = $test->questions()->with('options:id,online_test_question_id')->get()->mapWithKeys(fn (OnlineTestQuestion $question): array => [$question->id => $question->options->pluck('id')->all()]);
        $saved = $attempt->answers()->get()->keyBy('online_test_question_id');

        DB::transaction(function () use ($answers, $options, $saved, $attempt): void {
            foreach ($answers as $answer) {
                $optionId = isset($answer['option_id']) ? (int) $answer['option_id'] : null;

                if (! $options->has($answer['question_id']) || ($optionId !== null && ! in_array($optionId, $options[$answer['question_id']], true))) {
                    continue;
                }

                ($saved->get($answer['question_id']) ?? $attempt->answers()->make(['online_test_question_id' => $answer['question_id']]))
                    ->fill(['online_test_option_id' => $optionId])->save();
            }
        });

        return count($answers);
    }

    /**
     * Mark attempts whose time ran out without a submit (connection lost, tab closed), from their saved answers.
     */
    public function finishExpired(): int
    {
        $finished = 0;

        OnlineTestAttempt::where('status', 'in_progress')->with('test')->chunkById(100, function ($attempts) use (&$finished): void {
            foreach ($attempts as $attempt) {
                if ($attempt->test !== null && now()->gt($this->deadline($attempt->test, $attempt)->addMinutes(self::GRACE_MINUTES))) {
                    $this->finish($attempt->test, $attempt, []);
                    $finished++;
                }
            }
        });

        return $finished;
    }

    /**
     * Mark an attempt: the answers saved during the test, overridden by those sent with the submit.
     *
     * @param  array<int, int|null>  $chosen  question id => option id
     */
    private function finish(OnlineTest $test, OnlineTestAttempt $attempt, array $chosen): void
    {
        $questions = $test->questions()->with('options:id,online_test_question_id,is_correct')->get();
        $saved = $attempt->answers()->get()->keyBy('online_test_question_id');
        $enrollment = Enrollment::find($attempt->enrollment_id);

        DB::transaction(function () use ($test, $attempt, $questions, $chosen, $saved, $enrollment): void {
            $score = 0;
            $correct = 0;

            foreach ($questions as $question) {
                $optionId = array_key_exists($question->id, $chosen) ? $chosen[$question->id] : $saved->get($question->id)?->online_test_option_id;
                $optionId = $optionId !== null && $question->options->contains('id', (int) $optionId) ? (int) $optionId : null;
                $isCorrect = $optionId !== null && $question->options->firstWhere('id', $optionId)?->is_correct === true;
                ($saved->get($question->id) ?? $attempt->answers()->make(['online_test_question_id' => $question->id]))
                    ->fill(['online_test_option_id' => $optionId, 'is_correct' => $isCorrect])->save();
                $score += $isCorrect ? $question->marks : 0;
                $correct += $isCorrect ? 1 : 0;
            }

            $attempt->update(['submitted_at' => now(), 'score' => $score, 'correct_count' => $correct, 'status' => 'submitted']);

            if ($enrollment !== null) {
                $this->writeMark($test, $enrollment, $score);
            }
        });
    }

    /**
     * The student's submitted attempt: score, and the right answers when the teacher allows it.
     *
     * @return array<string, mixed>
     */
    public function review(OnlineTest $test, ?OnlineTestAttempt $attempt = null, ?User $user = null): array
    {
        $attempt ??= $test->attempts()->where('student_id', $this->ownEnrollment($user, $test)->student_id)->where('status', 'submitted')->firstOrFail();
        $answers = $attempt->answers()->get()->keyBy('online_test_question_id');

        return [
            'title' => $test->title,
            'score' => $attempt->score,
            'total_marks' => $test->total_marks,
            'correct_count' => $attempt->correct_count,
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'questions' => $test->show_answers ? $test->questions()->with('options')->get()->map(fn (OnlineTestQuestion $question): array => [
                'question' => $question->question,
                'marks' => $question->marks,
                'chosen_option_id' => $answers->get($question->id)?->online_test_option_id,
                'is_correct' => (bool) $answers->get($question->id)?->is_correct,
                'options' => $question->options->map(fn ($option): array => ['id' => $option->id, 'option_text' => $option->option_text, 'is_correct' => $option->is_correct])->all(),
            ])->all() : [],
        ];
    }

    /**
     * A score linked to an exam paper becomes the student's theory marks (unless the exam cell has
     * already approved marks for them).
     */
    private function writeMark(OnlineTest $test, Enrollment $enrollment, int $score): void
    {
        if ($test->exam_subject_id === null || $test->total_marks === 0) {
            return;
        }

        $paper = ExamSubject::with('exam.gradingScale.items')->find($test->exam_subject_id);
        $mark = Mark::firstOrNew(['exam_subject_id' => $test->exam_subject_id, 'enrollment_id' => $enrollment->id], ['section_id' => $enrollment->section_id]);

        if ($paper === null || $mark->status === 'approved') {
            return;
        }

        $mark->fill(['theory_marks' => round($score * $paper->theory_max_marks / $test->total_marks, 2), 'is_absent' => false, 'entered_by' => $test->created_by, 'status' => 'submitted']);
        $mark->grade = $paper->exam?->gradingScale?->gradeFor($paper->maxMarks() ? $mark->obtained() * 100 / $paper->maxMarks() : 0)?->grade;
        $mark->save();
    }

    private function deadline(OnlineTest $test, OnlineTestAttempt $attempt): Carbon
    {
        $byTimer = $attempt->started_at->copy()->addMinutes($test->duration_minutes);

        return $test->closes_at !== null && $test->closes_at->lt($byTimer) ? $test->closes_at->copy() : $byTimer;
    }

    /**
     * The signed-in student's current enrolment (and, for a test, that it is in the test's section).
     */
    private function ownEnrollment(?User $user, ?OnlineTest $test = null): Enrollment
    {
        $enrollment = Student::where('user_id', $user?->id ?? 0)->first()?->currentEnrollment;

        if ($enrollment === null || ($test !== null && ($enrollment->section_id !== $test->section_id || $test->status === 'draft'))) {
            abort(404, 'Test not found.');
        }

        return $enrollment;
    }

    private function ensureTeaches(User $user, int $sectionId, int $subjectId): void
    {
        if ($user->hasPermission('manage-exams')) {
            return;
        }

        $employeeId = Employee::where('user_id', $user->id)->value('id');

        if ($employeeId === null || ! TeacherAssignment::where('employee_id', $employeeId)->where('section_id', $sectionId)->where('subject_id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['section_id' => ['Only the subject teacher of this section can manage this test.']]);
        }
    }
}
