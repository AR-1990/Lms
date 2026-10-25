<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentConcession;
use App\Models\StudentTransport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Year-end promotion: prepares the next session (sections, curriculum, fee structures),
 * then moves each class's students up, keeps them in the same class, moves them down a class, or graduates them.
 */
class PromotionService
{
    public const ACTIONS = ['promote', 'repeat', 'demote', 'graduate'];

    /**
     * Old enrolment status for each move that opens a place in the new session.
     */
    private const MOVED_STATUS = ['promote' => 'promoted', 'repeat' => 'repeated', 'demote' => 'demoted'];

    /**
     * Start of the reason / remarks on concessions and transport carried into the new session; undo finds them by it.
     */
    private const CARRIED = 'Carried over from ';

    public function __construct(
        private CurriculumService $curriculumService,
        private FeeSetupService $feeSetupService,
        private StudentRecordService $studentService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Each class of the old session with how many students are still waiting to be moved.
     *
     * @return array<string, mixed>
     */
    public function summary(AcademicSession $from, AcademicSession $to): array
    {
        $counts = Enrollment::where('academic_session_id', $from->id)
            ->selectRaw('class_id, status, count(*) as total')
            ->groupBy('class_id', 'status')
            ->get()
            ->groupBy('class_id');

        $classes = SchoolClass::with('nextClass:id,name')->where('is_active', true)->orderBy('level_order')->get(['id', 'name', 'next_class_id'])
            ->map(function (SchoolClass $class) use ($counts): array {
                $byStatus = ($counts[$class->id] ?? collect())->pluck('total', 'status');

                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'next_class' => $class->nextClass?->only(['id', 'name']),
                    'waiting' => (int) ($byStatus['active'] ?? 0),
                    'moved' => (int) $byStatus->except(['active', 'withdrawn'])->sum(),
                ];
            })
            ->filter(fn (array $class): bool => $class['waiting'] + $class['moved'] > 0)
            ->values();

        $session = fn (AcademicSession $session): array => [
            ...$session->only(['id', 'name', 'is_current', 'admissions_open']),
            'starts_on' => $session->starts_on->toDateString(),
            'ends_on' => $session->ends_on->toDateString(),
        ];

        return [
            'from_session' => $session($from),
            'to_session' => $session($to),
            'to_sections' => Section::where('academic_session_id', $to->id)->count(),
            'classes' => $classes,
        ];
    }

    /**
     * The class's waiting students with their final result and the suggested move.
     *
     * @return array<string, mixed>
     */
    public function students(AcademicSession $from, AcademicSession $to, SchoolClass $class): array
    {
        $enrollments = Enrollment::with(['student:id,admission_no,first_name,last_name', 'section:id,name'])
            ->where('academic_session_id', $from->id)
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->orderBy('section_id')
            ->orderBy('roll_no')
            ->get(['id', 'student_id', 'section_id', 'roll_no']);

        $results = $this->finalResults($from, $enrollments->pluck('id')->all());
        $previous = $this->previousClass($class);

        return [
            'class' => $class->only(['id', 'name']),
            'next_class' => $class->nextClass?->only(['id', 'name']),
            'previous_class' => $previous?->only(['id', 'name']),
            'sections' => Section::where('academic_session_id', $to->id)
                ->whereIn('class_id', array_filter([$class->id, $class->next_class_id, $previous?->id]))
                ->orderBy('name')
                ->get(['id', 'class_id', 'name']),
            'students' => $enrollments->map(function (Enrollment $enrollment) use ($results, $class): array {
                $card = $results[$enrollment->id] ?? null;

                return [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'name' => $enrollment->student?->fullName(),
                    'admission_no' => $enrollment->student?->admission_no,
                    'section' => $enrollment->section?->name,
                    'roll_no' => $enrollment->roll_no,
                    'result' => $card?->result,
                    'percentage' => $card?->percentage,
                    'grade' => $card?->grade,
                    'suggested' => match (true) {
                        $card?->result === 'fail' => 'repeat',
                        $class->next_class_id === null => 'graduate',
                        default => 'promote',
                    },
                ];
            })->values(),
        ];
    }

    /**
     * Give the new session the old one's sections, curriculum and (for fee staff) fee structures.
     *
     * @return array{sections: int, curriculum: int, fee_structures: int|null}
     */
    public function prepare(AcademicSession $from, AcademicSession $to, User $user): array
    {
        $this->ensureDifferent($from, $to);

        return DB::transaction(function () use ($from, $to, $user): array {
            $existing = Section::where('academic_session_id', $to->id)->get(['class_id', 'name'])
                ->map(fn (Section $section): string => $section->class_id.'-'.mb_strtolower($section->name))
                ->flip();

            $sections = Section::where('academic_session_id', $from->id)->get()
                ->reject(fn (Section $section): bool => $existing->has($section->class_id.'-'.mb_strtolower($section->name)))
                ->each(fn (Section $section) => Section::create([
                    ...$section->only(['class_id', 'name', 'room', 'capacity', 'class_teacher_id']),
                    'academic_session_id' => $to->id,
                ]))
                ->count();

            $result = [
                'sections' => $sections,
                'curriculum' => $this->curriculumService->copy($from->id, $to->id),
                'fee_structures' => $user->hasPermission('manage-fee-setup') ? $this->feeSetupService->copyStructures($from->id, $to->id) : null,
            ];

            $this->activityLogger->record('promotions', 'prepare', "Prepared {$to->name} from {$from->name}: {$result['sections']} section(s), {$result['curriculum']} subject(s), ".($result['fee_structures'] ?? 0).' fee structure(s).', $to);

            return $result;
        });
    }

    /**
     * Move the chosen students of one class into the new session.
     *
     * Concessions that ended with the old session and active transport can follow the student.
     *
     * @param  array<int, array{enrollment_id: int, action: string, section_id?: int|null}>  $moves
     * @return array{promoted: int, repeated: int, demoted: int, graduated: int, concessions: int, transport: int}
     *
     * @throws ValidationException when a student is not waiting in this class or already has a place in the new session
     */
    public function promote(AcademicSession $from, AcademicSession $to, SchoolClass $class, array $moves, bool $carryConcessions = true, bool $carryTransport = true): array
    {
        $this->ensureDifferent($from, $to);

        $enrollments = Enrollment::with('student')
            ->where('academic_session_id', $from->id)
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->whereIn('id', array_column($moves, 'enrollment_id'))
            ->get()
            ->keyBy('id');

        if ($enrollments->count() !== count($moves)) {
            throw ValidationException::withMessages(['students' => ['Some students are no longer waiting in '.$class->name.'. Reload the list.']]);
        }

        $alreadyPlaced = Enrollment::where('academic_session_id', $to->id)->whereIn('student_id', $enrollments->pluck('student_id'))->exists();

        if ($alreadyPlaced) {
            throw ValidationException::withMessages(['students' => ['Some students already have a class in '.$to->name.'.']]);
        }

        $targets = ['promote' => $class->next_class_id, 'repeat' => $class->id, 'demote' => $this->previousClass($class)?->id];
        $actions = array_column($moves, 'action');

        if ($targets['promote'] === null && in_array('promote', $actions, true)) {
            throw ValidationException::withMessages(['students' => [$class->name.' has no next class. Set it on the class, or graduate these students.']]);
        }

        if ($targets['demote'] === null && in_array('demote', $actions, true)) {
            throw ValidationException::withMessages(['students' => ['No class leads into '.$class->name.', so these students cannot move down.']]);
        }

        $this->checkSections($to, $targets, $moves);
        $results = $this->finalResults($from, $enrollments->keys()->all());

        return DB::transaction(function () use ($from, $to, $class, $moves, $enrollments, $results, $targets, $carryConcessions, $carryTransport): array {
            $counts = ['promoted' => 0, 'repeated' => 0, 'demoted' => 0, 'graduated' => 0, 'concessions' => 0, 'transport' => 0];

            foreach ($moves as $move) {
                $old = $enrollments[$move['enrollment_id']];
                $resultStatus = $results[$old->id]?->result ?? null;

                if ($move['action'] === 'graduate') {
                    $this->graduate($old, $from, $resultStatus);
                    $counts['graduated']++;

                    continue;
                }

                // A place removed by "undo" is brought back instead of adding a second row for the same session.
                $new = Enrollment::withTrashed()->firstOrNew(['student_id' => $old->student_id, 'academic_session_id' => $to->id]);
                $new->fill(['class_id' => $targets[$move['action']], 'section_id' => $move['section_id'] ?? null, 'roll_no' => null, 'enrolled_on' => $to->starts_on, 'status' => 'active']);
                $new->deleted_at = null;
                $new->save();

                if ($carryTransport) {
                    $counts['transport'] += $this->carryTransport($old->student_id, $from, $to);
                }

                if ($carryConcessions) {
                    $counts['concessions'] += $this->carryConcessions($old->student_id, $from, $to);
                }

                $old->update([
                    'status' => self::MOVED_STATUS[$move['action']],
                    'promoted_to_enrollment_id' => $new->id,
                    'result_status' => $resultStatus,
                ]);

                $counts[self::MOVED_STATUS[$move['action']]]++;
            }

            $this->activityLogger->record('promotions', 'promote', "{$class->name} ({$from->name} → {$to->name}): {$counts['promoted']} promoted, {$counts['repeated']} kept in class, {$counts['demoted']} moved down, {$counts['graduated']} graduated.", $to);

            return $counts;
        });
    }

    /**
     * Put a class back as it was before it was moved, while nothing has been recorded in the new session yet.
     *
     * @throws ValidationException when nobody was moved, or attendance / challans / results / certificates already exist
     */
    public function undo(AcademicSession $from, AcademicSession $to, SchoolClass $class): int
    {
        $this->ensureDifferent($from, $to);

        $moved = Enrollment::with('student')
            ->where('academic_session_id', $from->id)
            ->where('class_id', $class->id)
            ->where(fn ($query) => $query->whereIn('status', array_values(self::MOVED_STATUS))->whereIn('promoted_to_enrollment_id', Enrollment::where('academic_session_id', $to->id)->select('id'))
                ->orWhere('status', 'graduated'))
            ->get();

        if ($moved->isEmpty()) {
            throw ValidationException::withMessages(['class_id' => ['Nobody from '.$class->name.' has been moved into '.$to->name.' yet.']]);
        }

        $newIds = $moved->pluck('promoted_to_enrollment_id')->filter()->all();
        $recorded = StudentAttendance::whereIn('enrollment_id', $newIds)->exists()
            || FeeInvoice::whereIn('enrollment_id', $newIds)->where('status', '!=', 'cancelled')->exists()
            || ReportCard::whereIn('enrollment_id', $newIds)->exists();

        if ($recorded) {
            throw ValidationException::withMessages(['class_id' => ['Attendance, challans or results are already recorded in '.$to->name.' for this class, so the move can no longer be undone. Change single students from their profile instead.']]);
        }

        if ($moved->contains(fn (Enrollment $enrollment): bool => $enrollment->status === 'graduated' && $enrollment->student?->slc_no !== null)) {
            throw ValidationException::withMessages(['class_id' => ['A graduate of '.$class->name.' already has a leaving certificate, so the move can no longer be undone.']]);
        }

        return DB::transaction(function () use ($from, $to, $class, $moved): int {
            foreach ($moved as $old) {
                if ($old->status === 'graduated') {
                    if ($old->student->current_status === 'graduated') {
                        $this->studentService->changeStatus($old->student, 'active', now()->toDateString(), null, 'Graduation undone.');
                    }
                } else {
                    Enrollment::whereKey($old->promoted_to_enrollment_id)->first()?->delete();
                    StudentTransport::where('student_id', $old->student_id)->where('academic_session_id', $to->id)->where('remarks', self::CARRIED.$from->name)->get()->each->delete();
                    StudentConcession::where('student_id', $old->student_id)->where('reason', self::CARRIED.$from->name)->whereDate('valid_from', $to->starts_on)
                        ->whereNotIn('id', FeeInvoiceItem::whereNotNull('student_concession_id')->select('student_concession_id'))->get()->each->delete();
                }

                // Rides closed at the end of the old session (by the carry-over or graduation) run on again.
                StudentTransport::where('student_id', $old->student_id)->where('academic_session_id', $from->id)->where('status', 'ended')->whereDate('to_date', $from->ends_on)->get()
                    ->each(fn (StudentTransport $ride) => $ride->update(['status' => 'active', 'to_date' => null]));

                $old->update(['status' => 'active', 'promoted_to_enrollment_id' => null, 'result_status' => null]);
            }

            $this->activityLogger->record('promotions', 'undo', "Undid the move of {$class->name} ({$from->name} → {$to->name}): {$moved->count()} student(s) back in {$from->name}.", $to);

            return $moved->count();
        });
    }

    /**
     * Number each section's students 1, 2, 3… by name or admission number.
     *
     * @throws ValidationException when no student of the class has a section yet
     */
    public function assignRollNumbers(AcademicSession $session, SchoolClass $class, string $order): int
    {
        $enrollments = Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->where('enrollments.academic_session_id', $session->id)
            ->where('enrollments.class_id', $class->id)
            ->where('enrollments.status', 'active')
            ->whereNotNull('enrollments.section_id')
            ->orderBy('enrollments.section_id')
            ->when($order === 'admission_no', fn ($query) => $query->orderBy('students.admission_no'), fn ($query) => $query->orderBy('students.first_name')->orderBy('students.last_name'))
            ->get(['enrollments.*']);

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages(['class_id' => ['No student of '.$class->name.' has a section in '.$session->name.' yet.']]);
        }

        DB::transaction(function () use ($enrollments): void {
            foreach ($enrollments->groupBy('section_id') as $section) {
                foreach ($section->values() as $index => $enrollment) {
                    $enrollment->update(['roll_no' => (string) ($index + 1)]);
                }
            }
        });

        $this->activityLogger->record('promotions', 'roll_numbers', "Roll numbers set for {$class->name} ({$session->name}) by ".($order === 'admission_no' ? 'admission number' : 'name').'.', $session);

        return $enrollments->count();
    }

    /**
     * End the student's active rides at the old session's end and start them again in the new one.
     */
    private function carryTransport(int $studentId, AcademicSession $from, AcademicSession $to): int
    {
        return StudentTransport::where('student_id', $studentId)->where('academic_session_id', $from->id)->where('status', 'active')->get()
            ->each(function (StudentTransport $ride) use ($from, $to): void {
                $ride->update(['status' => 'ended', 'to_date' => $from->ends_on]);
                StudentTransport::create([
                    ...$ride->only(['student_id', 'transport_route_id', 'transport_stop_id']),
                    'academic_session_id' => $to->id,
                    'from_date' => $to->starts_on,
                    'status' => 'active',
                    'remarks' => self::CARRIED.$from->name,
                ]);
            })
            ->count();
    }

    /**
     * Approved concessions that ran out with the old session are given again for the new one
     * (through the normal grant, so a type that needs approval waits for it again).
     */
    private function carryConcessions(int $studentId, AcademicSession $from, AcademicSession $to): int
    {
        return StudentConcession::where('student_id', $studentId)
            ->where('status', 'approved')
            ->whereNotNull('valid_to')
            ->whereDate('valid_to', '>=', $from->ends_on->copy()->subDays(31)->toDateString())
            ->whereDate('valid_to', '<', $to->starts_on->toDateString())
            ->get()
            ->each(fn (StudentConcession $concession) => $this->feeSetupService->grantConcession([
                ...$concession->only(['student_id', 'concession_type_id', 'fee_head_id', 'calculation', 'value']),
                'valid_from' => $to->starts_on->toDateString(),
                'valid_to' => $to->ends_on->toDateString(),
                'reason' => self::CARRIED.$from->name,
            ]))
            ->count();
    }

    private function graduate(Enrollment $old, AcademicSession $from, ?string $resultStatus): void
    {
        $student = $old->student;

        if ($student->current_status !== 'graduated') {
            $this->studentService->changeStatus($student, 'graduated', $from->ends_on->toDateString(), null, 'Graduated at the end of '.$from->name.'.');
        }

        $old->update(['status' => 'graduated', 'result_status' => $resultStatus]);
    }

    /**
     * Latest published final-term report card of each enrolment, keyed by enrolment id.
     *
     * @param  array<int, int>  $enrollmentIds
     * @return array<int, ReportCard>
     */
    private function finalResults(AcademicSession $from, array $enrollmentIds): array
    {
        return ReportCard::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->whereHas('exam', fn ($exam) => $exam->whereNotNull('published_at')
                ->whereHas('term', fn ($term) => $term->where('academic_session_id', $from->id)->where('is_final', true)))
            ->orderBy('id')
            ->get(['id', 'enrollment_id', 'result', 'percentage', 'grade'])
            ->keyBy('enrollment_id')
            ->all();
    }

    private function previousClass(SchoolClass $class): ?SchoolClass
    {
        return SchoolClass::where('next_class_id', $class->id)->orderByDesc('level_order')->first(['id', 'name']);
    }

    /**
     * @param  array<string, int|null>  $targets  new class for each action
     * @param  array<int, array{enrollment_id: int, action: string, section_id?: int|null}>  $moves
     */
    private function checkSections(AcademicSession $to, array $targets, array $moves): void
    {
        $sections = Section::where('academic_session_id', $to->id)->whereIn('id', array_filter(array_column($moves, 'section_id')))->pluck('class_id', 'id');

        foreach ($moves as $move) {
            if (empty($move['section_id']) || $move['action'] === 'graduate') {
                continue;
            }

            if (($sections[$move['section_id']] ?? null) !== $targets[$move['action']]) {
                throw ValidationException::withMessages(['students' => ['A chosen section does not belong to the new class in '.$to->name.'.']]);
            }
        }
    }

    private function ensureDifferent(AcademicSession $from, AcademicSession $to): void
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_session_id' => ['Choose a different session to move into.']]);
        }

        if ($to->starts_on->lte($from->starts_on)) {
            throw ValidationException::withMessages(['to_session_id' => ['The new session must start after '.$from->name.'.']]);
        }
    }
}
