<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExamTerm;
use App\Models\Mark;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Results: tabulation from marks, publishing report cards (totals, grade, pass/fail, positions,
 * term attendance), report card PDFs, and what students and parents see once published.
 */
class ResultService
{
    public function __construct(
        private ExamService $examService,
        private AttendanceService $attendanceService,
        private ActivityLogger $activityLogger,
        private PerformanceService $performanceService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Whole-class (or one section) marks table, ranked by percentage.
     *
     * @return array{papers: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}
     */
    public function tabulation(Exam $exam, ?int $sectionId): array
    {
        $exam = $this->examService->find($exam);
        $papers = $exam->subjects;
        $enrollments = $this->examService->enrollments($exam)->when($sectionId, fn (Collection $items) => $items->where('section_id', $sectionId));
        $marks = Mark::whereIn('exam_subject_id', $papers->pluck('id'))->whereIn('enrollment_id', $enrollments->pluck('id'))->get()->groupBy('enrollment_id');

        $rows = $enrollments->map(fn (Enrollment $enrollment): array => $this->summary($exam, $papers, $enrollment, $marks->get($enrollment->id, new Collection)))
            ->sortByDesc('percentage')->values();

        return [
            'papers' => $papers->map(fn (ExamSubject $paper): array => ['id' => $paper->id, 'subject' => $paper->subject?->name, 'max' => $paper->maxMarks()])->all(),
            'rows' => $this->rank($rows->all(), 'position'),
        ];
    }

    /**
     * Compute and save report cards for the whole class and mark the exam published.
     *
     * @throws ValidationException when marks are missing or not approved
     */
    public function publish(Exam $exam, User $user): Exam
    {
        $exam = $this->examService->find($exam);

        if (! in_array($exam->status, ['review', 'published'], true)) {
            throw ValidationException::withMessages(['status' => ['Move the exam to review before publishing results.']]);
        }

        $papers = $exam->subjects;
        $enrollments = $this->examService->enrollments($exam);
        $expected = $papers->count() * $enrollments->count();
        $approved = Mark::whereIn('exam_subject_id', $papers->pluck('id'))->whereIn('enrollment_id', $enrollments->pluck('id'))->where('status', 'approved')->count();

        if ($expected === 0 || $approved < $expected) {
            throw ValidationException::withMessages(['marks' => [($expected - $approved).' paper mark(s) are missing or not approved yet.']]);
        }

        $marks = Mark::whereIn('exam_subject_id', $papers->pluck('id'))->whereIn('enrollment_id', $enrollments->pluck('id'))->get()->groupBy('enrollment_id');
        $rows = $enrollments->map(fn (Enrollment $enrollment): array => $this->summary($exam, $papers, $enrollment, $marks->get($enrollment->id)))->all();
        $rows = $this->rank($rows, 'position_in_class');

        foreach (collect($rows)->groupBy('section_id') as $sectionRows) {
            foreach ($this->rank($sectionRows->all(), 'position_in_section') as $ranked) {
                $rows[array_search($ranked['enrollment_id'], array_column($rows, 'enrollment_id'), true)]['position_in_section'] = $ranked['position_in_section'];
            }
        }

        DB::transaction(function () use ($exam, $rows, $user): void {
            foreach ($rows as $row) {
                ReportCard::updateOrCreate(['exam_id' => $exam->id, 'enrollment_id' => $row['enrollment_id']], [
                    'student_id' => $row['student_id'],
                    'section_id' => $row['section_id'],
                    'total_max' => $row['total_max'],
                    'total_obtained' => $row['total_obtained'],
                    'percentage' => $row['percentage'],
                    'grade' => $row['grade'],
                    'position_in_section' => $row['position_in_section'] ?? null,
                    'position_in_class' => $row['position_in_class'],
                    'attendance_percent' => $this->attendancePercent($exam, $row['student_id']),
                    'result' => $row['result'],
                ]);
            }

            $exam->update(['status' => 'published', 'published_at' => now(), 'published_by' => $user->id]);
            $this->activityLogger->record('exams', 'results_published', "{$user->name} published results of {$exam->name} (".count($rows).' students)', $exam);
        });

        // New results change averages, ranks and risk levels in the performance tracker.
        $this->performanceService->compute(ExamTerm::findOrFail($exam->exam_term_id));

        foreach (Student::whereIn('id', array_column($rows, 'student_id'))->get(['id', 'first_name', 'middle_name', 'last_name']) as $student) {
            $this->notificationService->notifyEvent('result_published', $this->notificationService->familyUserIds([$student->id])->all(), [
                'student_name' => $student->fullName(), 'exam_name' => $exam->name,
            ], $exam);
        }

        return $exam->refresh();
    }

    /**
     * Published results for one student (parent / student portal, mobile app).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(Student $student): array
    {
        return ReportCard::where('student_id', $student->id)
            ->whereHas('exam', fn ($query) => $query->where('status', 'published'))
            ->with(['exam:id,name,exam_term_id,published_at', 'exam.term:id,name', 'exam.subjects.subject:id,name'])
            ->latest('id')
            ->get()
            ->map(function (ReportCard $card): array {
                $marks = Mark::where('enrollment_id', $card->enrollment_id)->whereIn('exam_subject_id', $card->exam->subjects->pluck('id'))->get()->keyBy('exam_subject_id');

                return [
                    'exam_id' => $card->exam_id,
                    'exam' => $card->exam->name,
                    'term' => $card->exam->term?->name,
                    'percentage' => $card->percentage,
                    'grade' => $card->grade,
                    'result' => $card->result,
                    'position_in_section' => $card->position_in_section,
                    'attendance_percent' => $card->attendance_percent,
                    'subjects' => $card->exam->subjects->map(fn (ExamSubject $paper): array => [
                        'subject' => $paper->subject?->name,
                        'max' => $paper->maxMarks(),
                        'obtained' => $marks->get($paper->id)?->is_absent ? null : $marks->get($paper->id)?->obtained(),
                        'absent' => (bool) $marks->get($paper->id)?->is_absent,
                        'grade' => $marks->get($paper->id)?->grade,
                    ])->all(),
                ];
            })->all();
    }

    public function reportCardsPdf(Exam $exam, ?int $sectionId, ?int $studentId = null): Response
    {
        $exam = $this->examService->find($exam);
        $cards = ReportCard::where('exam_id', $exam->id)->when($sectionId, fn ($query, int $id) => $query->where('section_id', $id))->when($studentId, fn ($query, int $id) => $query->where('student_id', $id))
            ->with(['student:id,first_name,last_name,admission_no', 'enrollment:id,roll_no,section_id', 'enrollment.section:id,name'])
            ->orderBy('section_id')->orderBy('position_in_section')->get();

        abort_if($cards->isEmpty(), 404, 'Results are not published for this exam yet.');

        $marks = Mark::whereIn('exam_subject_id', $exam->subjects->pluck('id'))->whereIn('enrollment_id', $cards->pluck('enrollment_id'))->get()->groupBy('enrollment_id');
        $this->activityLogger->record('exams', 'printed', (auth()->user()?->name ?? 'System').' printed '.$cards->count()." report cards for {$exam->name}", $exam);

        return Pdf::loadView('pdf.report-cards', [
            'exam' => $exam,
            'cards' => $cards,
            'marks' => $marks,
            'schoolName' => SystemSetting::query()->value('school_name'),
        ])->setPaper('a4')->stream('report-cards.pdf');
    }

    /**
     * @param  Collection<int, ExamSubject>  $papers
     * @param  Collection<int, Mark>|null  $marks
     * @return array<string, mixed>
     */
    private function summary(Exam $exam, Collection $papers, Enrollment $enrollment, ?Collection $marks): array
    {
        $byPaper = ($marks ?? new Collection)->keyBy('exam_subject_id');
        $totalMax = $papers->sum(fn (ExamSubject $paper): int => $paper->maxMarks());
        $obtained = $papers->sum(fn (ExamSubject $paper): float => $byPaper->get($paper->id)?->obtained() ?? 0);
        $percentage = $totalMax > 0 ? round($obtained * 100 / $totalMax, 2) : 0;
        $failed = $papers->contains(fn (ExamSubject $paper): bool => $byPaper->get($paper->id) === null
            || $byPaper->get($paper->id)->is_absent
            || ($paper->pass_marks !== null && $byPaper->get($paper->id)->obtained() < $paper->pass_marks));

        return [
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'section_id' => $enrollment->section_id,
            'name' => $enrollment->student?->fullName(),
            'admission_no' => $enrollment->student?->admission_no,
            'section' => $enrollment->section?->name,
            'marks' => $papers->mapWithKeys(fn (ExamSubject $paper): array => [$paper->id => $byPaper->get($paper->id)?->is_absent ? 'Abs' : $byPaper->get($paper->id)?->obtained()])->all(),
            'total_max' => $totalMax,
            'total_obtained' => $obtained,
            'percentage' => $percentage,
            'grade' => $exam->gradingScale?->gradeFor($percentage)?->grade,
            'result' => $failed ? 'fail' : 'pass',
        ];
    }

    /**
     * Competition ranking (1, 2, 2, 4) by percentage.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rank(array $rows, string $key): array
    {
        usort($rows, fn (array $a, array $b): int => $b['percentage'] <=> $a['percentage']);
        $previous = null;
        $position = 0;

        foreach ($rows as $index => $row) {
            if ($row['percentage'] !== $previous) {
                $position = $index + 1;
                $previous = $row['percentage'];
            }

            $rows[$index][$key] = $position;
        }

        return $rows;
    }

    private function attendancePercent(Exam $exam, int $studentId): ?float
    {
        if ($exam->term?->starts_on === null) {
            return null;
        }

        $to = $exam->term->ends_on && $exam->term->ends_on->isPast() ? $exam->term->ends_on : now();

        return $this->attendanceService->forStudent(Student::find($studentId), $exam->term->starts_on->toDateString(), $to->toDateString())['percentage'];
    }
}
