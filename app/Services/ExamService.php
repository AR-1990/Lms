<?php

namespace App\Services;

use App\Models\AdmitCard;
use App\Models\CardTemplate;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamHall;
use App\Models\ExamSeating;
use App\Models\ExamSubject;
use App\Models\FeeInvoice;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\PrintLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

/**
 * Exams for a class in a term: date sheet, exam fee challans, admit cards (fee / attendance
 * eligibility with admin override), seating plans and the status flow up to marks entry.
 */
class ExamService
{
    /**
     * Steps an exam may move to by hand; `published` only comes from publishing results.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'draft' => ['scheduled'],
        'scheduled' => ['draft', 'admit_cards_issued', 'marks_entry'],
        'admit_cards_issued' => ['marks_entry'],
        'marks_entry' => ['review'],
        'review' => ['marks_entry'],
        'published' => [],
    ];

    public function __construct(
        private FeeInvoiceService $invoiceService,
        private CardService $cardService,
        private AttendanceService $attendanceService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  array{exam_term_id?: int|null, class_id?: int|null, status?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        return Exam::query()
            ->with(['term:id,name,academic_session_id', 'schoolClass:id,name'])
            ->withCount('subjects')
            ->when($filters['exam_term_id'] ?? null, fn (Builder $query, int $id) => $query->where('exam_term_id', $id))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $id) => $query->where('class_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query($filters))
            ->escapeColumns([])
            ->addColumn('term_name', fn (Exam $exam): ?string => $exam->term?->name)
            ->addColumn('class_name', fn (Exam $exam): ?string => $exam->schoolClass?->name)
            ->addColumn('next_statuses', fn (Exam $exam): array => self::TRANSITIONS[$exam->status] ?? [])
            ->removeColumn('term', 'school_class')
            ->toArray();
    }

    public function find(Exam $exam): Exam
    {
        return $exam->load([
            'term:id,name,academic_session_id,starts_on,ends_on', 'schoolClass:id,name', 'gradingScale.items', 'feeHead:id,name', 'examType:id,name',
            'subjects' => fn ($query) => $query->orderBy('exam_date')->orderBy('starts_at'), 'subjects.subject:id,name',
        ])->loadCount(['admitCards', 'admitCards as eligible_count' => fn (Builder $query) => $query->where('is_eligible', true)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(?Exam $exam, array $data): Exam
    {
        return DB::transaction(function () use ($exam, $data): Exam {
            if ($exam === null) {
                $exam = Exam::create([
                    ...$data,
                    'grading_scale_id' => $data['grading_scale_id'] ?? GradingScale::where('is_default', true)->value('id'),
                    'status' => 'draft',
                ]);
            } else {
                $exam->update($data);
            }

            return $this->find($exam);
        });
    }

    /**
     * @throws ValidationException when marks were entered or results published
     */
    public function delete(Exam $exam): void
    {
        if ($exam->status === 'published' || Mark::whereIn('exam_subject_id', $exam->subjects()->select('id'))->exists()) {
            throw ValidationException::withMessages(['exam' => ['Marks are already entered for this exam; it cannot be deleted.']]);
        }

        DB::transaction(function () use ($exam): void {
            $exam->subjects()->delete();
            $exam->delete();
        });
    }

    /**
     * Replace the date sheet. Papers that already have marks stay (they can be edited, not removed).
     *
     * @param  array<int, array<string, mixed>>  $papers
     *
     * @throws ValidationException when a paper with marks would be removed
     */
    public function saveDateSheet(Exam $exam, array $papers): Exam
    {
        $keep = collect($papers)->pluck('subject_id')->all();
        $removing = $exam->subjects()->whereNotIn('subject_id', $keep)->pluck('id');

        if ($removing->isNotEmpty() && Mark::whereIn('exam_subject_id', $removing)->exists()) {
            throw ValidationException::withMessages(['papers' => ['A paper with entered marks cannot be removed from the date sheet.']]);
        }

        DB::transaction(function () use ($exam, $papers, $removing): void {
            ExamSubject::whereIn('id', $removing)->delete();

            foreach ($papers as $paper) {
                ExamSubject::updateOrCreate(['exam_id' => $exam->id, 'subject_id' => $paper['subject_id']], $paper);
            }

            $this->activityLogger->record('exams', 'date_sheet_saved', (auth()->user()?->name ?? 'System')." saved the date sheet of {$exam->name}", $exam);
        });

        return $this->find($exam);
    }

    /**
     * @throws ValidationException for steps that are not allowed or a schedule without papers
     */
    public function changeStatus(Exam $exam, string $status): Exam
    {
        if (! in_array($status, self::TRANSITIONS[$exam->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => ["An exam cannot move from {$exam->status} to {$status}."]]);
        }

        if ($status === 'scheduled' && $exam->subjects()->doesntExist()) {
            throw ValidationException::withMessages(['status' => ['Add the date sheet before scheduling.']]);
        }

        DB::transaction(fn () => $exam->update(['status' => $status]));

        return $this->find($exam);
    }

    /**
     * Exam fee challans for every active student of the class (once per student).
     *
     * @return array{issued: int, skipped: int}
     *
     * @throws ValidationException when the exam has no fee
     */
    public function generateFeeChallans(Exam $exam): array
    {
        if ($exam->fee_head_id === null || ! $exam->exam_fee_amount) {
            throw ValidationException::withMessages(['exam' => ['Set the exam fee head and amount first.']]);
        }

        $issued = 0;
        $skipped = 0;

        DB::transaction(function () use ($exam, &$issued, &$skipped): void {
            foreach ($this->enrollments($exam) as $enrollment) {
                $this->invoiceService->forExam($exam, $enrollment) === null ? $skipped++ : $issued++;
            }
        });

        return ['issued' => $issued, 'skipped' => $skipped];
    }

    /**
     * Create / refresh admit cards. Students owing fees (when clearance is required) or below the
     * attendance rule are marked ineligible until an administrator overrides.
     *
     * @return array{eligible: int, ineligible: int}
     */
    public function issueAdmitCards(Exam $exam): array
    {
        $exam->loadMissing('term');
        $enrollments = $this->enrollments($exam)->sortBy(fn (Enrollment $enrollment): string => ($enrollment->section?->name ?? '').str_pad((string) $enrollment->roll_no, 6, '0', STR_PAD_LEFT))->values();
        $owing = $exam->require_fee_clearance
            ? FeeInvoice::whereIn('student_id', $enrollments->pluck('student_id'))->whereIn('status', FeeInvoice::OPEN_STATUSES)->where('balance', '>', 0)->pluck('student_id')->unique()->all()
            : [];

        $counts = ['eligible' => 0, 'ineligible' => 0];

        DB::transaction(function () use ($exam, $enrollments, $owing, &$counts): void {
            foreach ($enrollments as $position => $enrollment) {
                $reason = in_array($enrollment->student_id, $owing, true) ? 'fee_due' : $this->attendanceShortfall($exam, $enrollment);
                $card = AdmitCard::where('exam_id', $exam->id)->where('enrollment_id', $enrollment->id)->first();

                $attributes = [
                    'is_eligible' => $reason === null || $card?->override_by !== null,
                    'ineligible_reason' => $reason,
                    'issued_at' => $card?->issued_at ?? now(),
                ];

                if ($card === null) {
                    $card = AdmitCard::create([...$attributes, 'exam_id' => $exam->id, 'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'exam_roll_no' => sprintf('E%d-%03d', $exam->id, $position + 1)]);
                } else {
                    $card->update($attributes);
                }

                $card->is_eligible ? $counts['eligible']++ : $counts['ineligible']++;
            }

            if ($exam->status === 'scheduled') {
                $exam->update(['status' => 'admit_cards_issued']);
            }
        });

        return $counts;
    }

    /**
     * @return Collection<int, AdmitCard>
     */
    public function admitCards(Exam $exam): Collection
    {
        return AdmitCard::where('exam_id', $exam->id)->with(['student:id,first_name,last_name,admission_no', 'enrollment.section:id,name', 'enrollment:id,section_id,roll_no'])->orderBy('exam_roll_no')->get();
    }

    public function overrideAdmitCard(AdmitCard $card, string $reason, User $user): AdmitCard
    {
        DB::transaction(fn () => $card->update(['is_eligible' => true, 'override_by' => $user->id, 'override_reason' => $reason]));

        return $card;
    }

    /**
     * A5 roll number slips with the date sheet for eligible students; every print is logged.
     */
    public function admitCardsPdf(Exam $exam, ?int $sectionId): Response
    {
        $exam = $this->find($exam);
        $cards = AdmitCard::where('exam_id', $exam->id)->where('is_eligible', true)
            ->when($sectionId, fn (Builder $query, int $id) => $query->whereHas('enrollment', fn (Builder $inner) => $inner->where('section_id', $id)))
            ->with(['student:id,first_name,last_name,admission_no,photo_path', 'student.guardians', 'enrollment.section:id,name'])
            ->orderBy('exam_roll_no')->get();

        abort_if($cards->isEmpty(), 404, 'No eligible admit cards to print.');

        DB::transaction(function () use ($cards, $exam): void {
            foreach ($cards as $card) {
                PrintLog::create(['document_type' => 'admit_card', 'printable_type' => AdmitCard::class, 'printable_id' => $card->id, 'printed_by' => auth()->id(), 'copies' => 1, 'is_reprint' => $card->printed_count > 0, 'created_at' => now()]);
                $card->forceFill(['printed_count' => $card->printed_count + 1])->saveQuietly();
            }

            $this->activityLogger->record('exams', 'printed', (auth()->user()?->name ?? 'System').' printed '.$cards->count()." admit cards for {$exam->name}", $exam);
        });

        $template = CardTemplate::where('card_type', 'admit_card')->where('is_active', true)->orderByDesc('is_default')->with('fields')->first();
        abort_if($template === null, 422, 'Create an admit card template first.');

        $schoolName = SystemSetting::query()->value('school_name');
        $dateSheet = $exam->subjects->map(fn (ExamSubject $paper): array => [
            'subject' => $paper->subject?->name,
            'date' => $paper->exam_date?->format('d M (D)') ?? '—',
            'time' => $paper->starts_at ? substr($paper->starts_at, 0, 5) : '—',
        ])->all();

        return $this->cardService->renderSheet($cards->map(fn (AdmitCard $card): array => [
            'template' => $template,
            'values' => [
                'school_name' => $schoolName,
                'exam_name' => $exam->name,
                'full_name' => $card->student?->fullName(),
                'father_name' => $this->cardService->labelled('Parent', $card->student?->guardians->first()?->name),
                'admission_no' => $this->cardService->labelled('Adm No', $card->student?->admission_no),
                'class_section' => $this->cardService->labelled('Class', trim($exam->schoolClass->name.' '.($card->enrollment?->section?->name ?? ''))),
                'exam_roll_no' => $this->cardService->labelled('Roll No', $card->exam_roll_no),
                'date_sheet' => $dateSheet,
                'instructions' => $exam->instructions,
                'photo' => $card->student?->photo_path && Storage::disk('public')->exists($card->student->photo_path) ? Storage::disk('public')->path($card->student->photo_path) : null,
            ],
        ]), 'admit-cards.pdf');
    }

    /**
     * Seat eligible students hall by hall in roll-number order; replaces the paper's plan.
     *
     * @param  array<int, int>  $hallIds
     * @return array{seated: int, halls: int}
     *
     * @throws ValidationException when the halls are too small
     */
    public function generateSeating(ExamSubject $paper, array $hallIds): array
    {
        $halls = ExamHall::whereIn('id', $hallIds)->where('is_active', true)->orderBy('name')->get();
        $cards = AdmitCard::where('exam_id', $paper->exam_id)->where('is_eligible', true)->orderBy('exam_roll_no')->get(['enrollment_id', 'exam_roll_no']);

        if ($cards->isEmpty()) {
            throw ValidationException::withMessages(['exam' => ['Issue admit cards before making the seating plan.']]);
        }

        if ($halls->sum('capacity') < $cards->count()) {
            throw ValidationException::withMessages(['hall_ids' => ["The chosen halls seat {$halls->sum('capacity')}; {$cards->count()} students are eligible."]]);
        }

        DB::transaction(function () use ($paper, $halls, $cards): void {
            ExamSeating::where('exam_subject_id', $paper->id)->delete();
            $queue = $cards->values();
            $index = 0;

            foreach ($halls as $hall) {
                $columns = max(1, (int) ($hall->columns ?: $hall->capacity));

                for ($seat = 0; $seat < $hall->capacity && $index < $queue->count(); $seat++, $index++) {
                    ExamSeating::create([
                        'exam_subject_id' => $paper->id,
                        'exam_hall_id' => $hall->id,
                        'enrollment_id' => $queue[$index]->enrollment_id,
                        'seat_no' => (string) ($seat + 1),
                        'row_no' => intdiv($seat, $columns) + 1,
                        'column_no' => $seat % $columns + 1,
                    ]);
                }
            }

            $this->activityLogger->record('exams', 'seating_generated', (auth()->user()?->name ?? 'System')." made the seating plan for paper #{$paper->id}", $paper);
        });

        return ['seated' => $cards->count(), 'halls' => $halls->count()];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function seating(ExamSubject $paper): array
    {
        return ExamSeating::where('exam_subject_id', $paper->id)
            ->with(['hall:id,name', 'enrollment:id,student_id,section_id,roll_no', 'enrollment.student:id,first_name,last_name', 'enrollment.section:id,name'])
            ->orderBy('exam_hall_id')->orderByRaw('cast(seat_no as unsigned)')
            ->get()
            ->map(fn (ExamSeating $seat): array => [
                'hall' => $seat->hall?->name,
                'seat_no' => $seat->seat_no,
                'row_no' => $seat->row_no,
                'column_no' => $seat->column_no,
                'student' => $seat->enrollment?->student?->fullName(),
                'section' => $seat->enrollment?->section?->name,
                'roll_no' => $seat->enrollment?->roll_no,
            ])->all();
    }

    /**
     * Active enrolments of the exam's class in the term's session.
     *
     * @return Collection<int, Enrollment>
     */
    public function enrollments(Exam $exam): Collection
    {
        $exam->loadMissing('term');

        return Enrollment::where('class_id', $exam->class_id)->where('academic_session_id', $exam->term->academic_session_id)->where('status', 'active')
            ->whereHas('student', fn (Builder $query) => $query->where('current_status', 'active'))
            ->with(['student:id,first_name,last_name,admission_no', 'section:id,name'])
            ->get();
    }

    private function attendanceShortfall(Exam $exam, Enrollment $enrollment): ?string
    {
        if ($exam->min_attendance_percent === null || $exam->term->starts_on === null) {
            return null;
        }

        $percent = $this->attendanceService->forStudent($enrollment->student, $exam->term->starts_on->toDateString(), now()->toDateString())['percentage'];

        return $percent !== null && $percent < $exam->min_attendance_percent ? 'low_attendance' : null;
    }
}
