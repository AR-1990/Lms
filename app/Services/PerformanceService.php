<?php

namespace App\Services;

use App\Models\AssessmentSkill;
use App\Models\DiaryStudentRemark;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExamTerm;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Mark;
use App\Models\PerformanceAlert;
use App\Models\PerformanceAlertRule;
use App\Models\PerformanceSnapshot;
use App\Models\ReportCard;
use App\Models\Section;
use App\Models\SkillAssessment;
use App\Models\Student;
use App\Models\StudentGoal;
use App\Models\SubjectPerformanceSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Student performance tracker: per-term snapshots built from data already recorded (results,
 * attendance, homework, diary remarks), skill ratings, goals and "at risk" alerts from the
 * school's own rules. Snapshots are rebuilt after results are published and every night.
 */
class PerformanceService
{
    public function __construct(private AttendanceService $attendanceService) {}

    /**
     * Rebuild term snapshots (and alerts) for a term, optionally one section.
     *
     * @return int number of students
     */
    public function compute(ExamTerm $term, ?int $sectionId = null): int
    {
        $enrollments = Enrollment::where('academic_session_id', $term->academic_session_id)->where('status', 'active')->whereNotNull('section_id')
            ->when($sectionId, fn (Builder $query, int $id) => $query->where('section_id', $id))
            ->with('student:id,first_name,last_name')
            ->get();

        if ($enrollments->isEmpty()) {
            return 0;
        }

        $from = $term->starts_on?->toDateString() ?? now()->startOfYear()->toDateString();
        $to = min($term->ends_on?->toDateString() ?? now()->toDateString(), now()->toDateString());
        $exams = Exam::where('exam_term_id', $term->id)->where('status', 'published')->pluck('id');
        $cards = ReportCard::whereIn('exam_id', $exams)->get()->groupBy('enrollment_id');
        $classAverages = ReportCard::whereIn('exam_id', $exams)->get()->groupBy('exam_id')->map(fn (Collection $items): float => round($items->avg('percentage'), 2));
        $papers = ExamSubject::whereIn('exam_id', $exams)->get();
        $marks = Mark::whereIn('exam_subject_id', $papers->pluck('id'))->get();
        $subjectStats = $this->subjectStats($papers, $marks);
        $rules = PerformanceAlertRule::where('is_active', true)->get();
        $previousTerm = ExamTerm::where('academic_session_id', $term->academic_session_id)->where('sort_order', '<', $term->sort_order)->orderByDesc('sort_order')->first();

        DB::transaction(function () use ($enrollments, $term, $from, $to, $cards, $classAverages, $marks, $papers, $subjectStats, $rules, $previousTerm): void {
            foreach ($enrollments as $enrollment) {
                $studentCards = $cards->get($enrollment->id, collect());
                $attendance = $this->attendanceService->forStudent($enrollment->student, $from, $to);
                [$completion, $lateSubmissions] = $this->homework($enrollment, $from, $to);
                [$appreciation, $discipline] = $this->remarks($enrollment->student_id, $from, $to);

                $figures = [
                    'student_id' => $enrollment->student_id,
                    'section_id' => $enrollment->section_id,
                    'exam_term_id' => $term->id,
                    'attendance_percent' => $attendance['percentage'],
                    'late_count' => $attendance['counts']['late'] ?? 0,
                    'average_percent' => $studentCards->isEmpty() ? null : round($studentCards->avg('percentage'), 2),
                    'class_average_percent' => $studentCards->isEmpty() ? null : round($studentCards->map(fn (ReportCard $card): ?float => $classAverages->get($card->exam_id))->avg(), 2),
                    'rank_in_section' => $studentCards->isEmpty() ? null : (int) round($studentCards->avg('position_in_section')),
                    'homework_completion_percent' => $completion,
                    'late_submissions' => $lateSubmissions,
                    'appreciation_count' => $appreciation,
                    'discipline_count' => $discipline,
                    'computed_at' => now(),
                ];

                $triggered = $rules->filter(fn (PerformanceAlertRule $rule): bool => $rule->matches($figures[$rule->metric] ?? null));
                $figures['risk_level'] = $triggered->isNotEmpty() ? 'at_risk' : ($this->nearThreshold($rules, $figures) ? 'watch' : 'on_track');

                $snapshot = PerformanceSnapshot::updateOrCreate(['enrollment_id' => $enrollment->id, 'period_type' => 'term', 'period_start' => $from], $figures);
                $this->saveSubjects($snapshot, $enrollment, $papers, $marks, $subjectStats, $previousTerm);

                foreach ($triggered as $rule) {
                    PerformanceAlert::firstOrCreate(
                        ['enrollment_id' => $enrollment->id, 'performance_alert_rule_id' => $rule->id, 'exam_term_id' => $term->id, 'status' => 'open'],
                        ['student_id' => $enrollment->student_id, 'metric_value' => $figures[$rule->metric]],
                    );
                }
            }
        });

        return $enrollments->count();
    }

    /**
     * Section overview for a term: each student's figures and risk level.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(Section $section, ExamTerm $term): array
    {
        return PerformanceSnapshot::where('section_id', $section->id)->where('exam_term_id', $term->id)->where('period_type', 'term')
            ->with('student:id,first_name,last_name,admission_no')
            ->withCount(['subjects'])
            ->get()
            ->map(fn (PerformanceSnapshot $snapshot): array => [
                'student_id' => $snapshot->student_id,
                'name' => $snapshot->student?->fullName(),
                'admission_no' => $snapshot->student?->admission_no,
                ...$snapshot->only(['attendance_percent', 'late_count', 'average_percent', 'class_average_percent', 'rank_in_section', 'homework_completion_percent', 'late_submissions', 'appreciation_count', 'discipline_count', 'risk_level']),
                'open_alerts' => PerformanceAlert::where('enrollment_id', $snapshot->enrollment_id)->where('status', '!=', 'resolved')->count(),
            ])
            ->sortBy(fn (array $row): string => ['at_risk' => '0', 'watch' => '1', 'on_track' => '2'][$row['risk_level']].$row['name'])
            ->values()
            ->all();
    }

    /**
     * Everything the student / parent / teacher sees for one student.
     *
     * @return array<string, mixed>
     */
    public function forStudent(Student $student): array
    {
        $snapshots = PerformanceSnapshot::where('student_id', $student->id)->where('period_type', 'term')
            ->with(['term:id,name,sort_order', 'subjects.subject:id,name'])
            ->orderBy('period_start')
            ->get();

        return [
            'student' => ['id' => $student->id, 'name' => $student->fullName(), 'admission_no' => $student->admission_no],
            'terms' => $snapshots->map(fn (PerformanceSnapshot $snapshot): array => [
                'term' => $snapshot->term?->name,
                ...$snapshot->only(['attendance_percent', 'late_count', 'average_percent', 'class_average_percent', 'rank_in_section', 'homework_completion_percent', 'late_submissions', 'appreciation_count', 'discipline_count', 'risk_level']),
                'computed_at' => $snapshot->computed_at?->toIso8601String(),
                'subjects' => $snapshot->subjects->map(fn (SubjectPerformanceSnapshot $subject): array => [
                    'subject' => $subject->subject?->name,
                    ...$subject->only(['average_percent', 'highest_percent', 'class_average_percent', 'change_from_previous']),
                ])->all(),
            ])->all(),
            'skills' => SkillAssessment::whereIn('enrollment_id', Enrollment::where('student_id', $student->id)->select('id'))
                ->with(['skill:id,name', 'rating:id,name'])->latest('exam_term_id')->get()
                ->unique('assessment_skill_id')->values()
                ->map(fn (SkillAssessment $assessment): array => ['skill' => $assessment->skill?->name, 'rating' => $assessment->rating?->name, 'comment' => $assessment->comment])->all(),
            'goals' => StudentGoal::where('student_id', $student->id)->with('subject:id,name')->latest('id')->get()
                ->map(fn (StudentGoal $goal): array => [...$goal->only(['id', 'title', 'target_value', 'achieved_value', 'status', 'notes']), 'subject' => $goal->subject?->name, 'target_date' => $goal->target_date?->toDateString()])->all(),
            'alerts' => PerformanceAlert::where('student_id', $student->id)->where('status', '!=', 'resolved')->with('rule:id,name,metric,operator,threshold')->latest('id')->get()
                ->map(fn (PerformanceAlert $alert): array => ['id' => $alert->id, 'rule' => $alert->rule?->name, 'metric_value' => $alert->metric_value, 'status' => $alert->status])->all(),
        ];
    }

    /**
     * Skill rating grid for a section and term.
     *
     * @return array{skills: array<int, array{id: int, name: string}>, rows: array<int, array<string, mixed>>}
     */
    public function skillSheet(Section $section, ExamTerm $term): array
    {
        $skills = AssessmentSkill::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
        $ratings = SkillAssessment::where('exam_term_id', $term->id)->whereIn('enrollment_id', Enrollment::where('section_id', $section->id)->select('id'))->get()
            ->groupBy('enrollment_id');

        return [
            'skills' => $skills->map(fn (AssessmentSkill $skill): array => ['id' => $skill->id, 'name' => $skill->name])->all(),
            'rows' => Enrollment::where('section_id', $section->id)->where('status', 'active')->with('student:id,first_name,last_name')->orderBy('roll_no')->get(['id', 'student_id', 'roll_no'])
                ->map(fn (Enrollment $enrollment): array => [
                    'enrollment_id' => $enrollment->id,
                    'name' => $enrollment->student->fullName(),
                    'ratings' => $ratings->get($enrollment->id, collect())->pluck('rating_id', 'assessment_skill_id')->all(),
                ])->all(),
        ];
    }

    /**
     * @param  array<int, array{enrollment_id: int, assessment_skill_id: int, rating_id: int|null, comment?: string|null}>  $rows
     *
     * @throws ValidationException when a student is not in the section
     */
    public function saveSkills(Section $section, ExamTerm $term, array $rows, User $user): int
    {
        $allowed = Enrollment::where('section_id', $section->id)->pluck('id')->all();

        foreach ($rows as $row) {
            if (! in_array($row['enrollment_id'], $allowed, true)) {
                throw ValidationException::withMessages(['rows' => ['A student in the list is not in this section.']]);
            }
        }

        return DB::transaction(function () use ($term, $rows, $user): int {
            $saved = 0;

            foreach ($rows as $row) {
                $assessment = SkillAssessment::firstOrNew(['enrollment_id' => $row['enrollment_id'], 'exam_term_id' => $term->id, 'assessment_skill_id' => $row['assessment_skill_id']]);
                $assessment->fill(['rating_id' => $row['rating_id'], 'comment' => $row['comment'] ?? null, 'assessed_by' => $user->id]);

                if ($assessment->isDirty()) {
                    $assessment->save();
                    $saved++;
                }
            }

            return $saved;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveGoal(?StudentGoal $goal, array $data, User $user): StudentGoal
    {
        return DB::transaction(function () use ($goal, $data, $user): StudentGoal {
            if ($goal === null) {
                $student = Student::findOrFail($data['student_id']);
                $goal = StudentGoal::create([...$data, 'academic_session_id' => $student->currentEnrollment?->academic_session_id ?? Enrollment::where('student_id', $student->id)->latest('id')->value('academic_session_id'), 'set_by' => $user->id, 'status' => 'active']);
            } else {
                $goal->update($data);
            }

            return $goal->load('subject:id,name');
        });
    }

    public function deleteGoal(StudentGoal $goal): void
    {
        DB::transaction(fn () => $goal->delete());
    }

    public function updateAlert(PerformanceAlert $alert, string $status, ?string $action, User $user): PerformanceAlert
    {
        DB::transaction(fn () => $alert->update([
            'status' => $status,
            'action_taken' => $action ?? $alert->action_taken,
            'resolved_by' => $status === 'resolved' ? $user->id : $alert->resolved_by,
            'resolved_at' => $status === 'resolved' ? now() : $alert->resolved_at,
        ]));

        return $alert;
    }

    /**
     * Per exam paper: highest and class average percentage.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, ExamSubject>  $papers
     * @param  \Illuminate\Database\Eloquent\Collection<int, Mark>  $marks
     * @return Collection<int, array{highest: float, average: float}>
     */
    private function subjectStats($papers, $marks): Collection
    {
        return $papers->mapWithKeys(function (ExamSubject $paper) use ($marks): array {
            $percents = $marks->where('exam_subject_id', $paper->id)->map(fn (Mark $mark): float => $paper->maxMarks() ? $mark->obtained() * 100 / $paper->maxMarks() : 0);

            return [$paper->id => ['highest' => round((float) $percents->max(), 2), 'average' => round((float) $percents->avg(), 2)]];
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ExamSubject>  $papers
     * @param  \Illuminate\Database\Eloquent\Collection<int, Mark>  $marks
     * @param  Collection<int, array{highest: float, average: float}>  $stats
     */
    private function saveSubjects(PerformanceSnapshot $snapshot, Enrollment $enrollment, $papers, $marks, Collection $stats, ?ExamTerm $previousTerm): void
    {
        $previous = $previousTerm === null ? collect() : SubjectPerformanceSnapshot::whereIn('performance_snapshot_id', PerformanceSnapshot::where('enrollment_id', $enrollment->id)->where('exam_term_id', $previousTerm->id)->select('id'))
            ->pluck('average_percent', 'subject_id');

        foreach ($papers->groupBy('subject_id') as $subjectId => $subjectPapers) {
            $percents = $subjectPapers->map(function (ExamSubject $paper) use ($marks, $enrollment): ?float {
                $mark = $marks->first(fn (Mark $item): bool => $item->exam_subject_id === $paper->id && $item->enrollment_id === $enrollment->id);

                return $mark === null || ! $paper->maxMarks() ? null : $mark->obtained() * 100 / $paper->maxMarks();
            })->filter(fn (?float $value): bool => $value !== null);

            if ($percents->isEmpty()) {
                continue;
            }

            $average = round($percents->avg(), 2);

            SubjectPerformanceSnapshot::updateOrCreate(['performance_snapshot_id' => $snapshot->id, 'subject_id' => $subjectId], [
                'average_percent' => $average,
                'highest_percent' => $subjectPapers->max(fn (ExamSubject $paper): float => $stats->get($paper->id)['highest']),
                'class_average_percent' => round($subjectPapers->avg(fn (ExamSubject $paper): float => $stats->get($paper->id)['average']), 2),
                'change_from_previous' => $previous->has($subjectId) ? round($average - (float) $previous->get($subjectId), 2) : null,
            ]);
        }
    }

    /**
     * @return array{0: float|null, 1: int} homework completion % and late submissions
     */
    private function homework(Enrollment $enrollment, string $from, string $to): array
    {
        $homeworkIds = Homework::where('section_id', $enrollment->section_id)->whereBetween('assigned_on', [$from, $to])->pluck('id');

        if ($homeworkIds->isEmpty()) {
            return [null, 0];
        }

        $submissions = HomeworkSubmission::whereIn('homework_id', $homeworkIds)->where('student_id', $enrollment->student_id)->whereIn('status', ['submitted', 'late', 'graded'])->get(['status']);

        return [round($submissions->count() * 100 / $homeworkIds->count(), 2), $submissions->where('status', 'late')->count()];
    }

    /**
     * Diary remarks counted by their remark type's code (positive / negative).
     *
     * @return array{0: int, 1: int}
     */
    private function remarks(int $studentId, string $from, string $to): array
    {
        $codes = DiaryStudentRemark::where('student_id', $studentId)
            ->whereHas('diary', fn (Builder $query) => $query->whereBetween('diary_date', [$from, $to]))
            ->with('remarkType:id,code')
            ->get()
            ->map(fn (DiaryStudentRemark $remark): ?string => $remark->remarkType?->code);

        return [$codes->filter(fn (?string $code): bool => $code === 'positive')->count(), $codes->filter(fn (?string $code): bool => $code === 'negative')->count()];
    }

    /**
     * Within 10 % of a rule's threshold on the wrong side of "fine": worth watching.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PerformanceAlertRule>  $rules
     * @param  array<string, mixed>  $figures
     */
    private function nearThreshold($rules, array $figures): bool
    {
        return $rules->contains(function (PerformanceAlertRule $rule) use ($figures): bool {
            $value = $figures[$rule->metric] ?? null;
            $margin = max(abs($rule->threshold) * 0.1, 1);

            return $value !== null && (in_array($rule->operator, ['<', '<='], true) ? $value < $rule->threshold + $margin : $value > $rule->threshold - $margin);
        });
    }
}
