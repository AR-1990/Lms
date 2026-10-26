<?php

namespace App\Services;

use App\Http\Resources\TimetableSlotResource;
use App\Models\AdmitCard;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\StudentLeaveRequest;
use App\Models\StudentTransport;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What a parent or the student sees for one child: class, timetable, exams with admit cards and
 * report cards, fees with challans and receipts, leave requests and transport — one call for the
 * portal page and the mobile app. Attendance, diary, results and the year-by-year record have their own endpoints.
 */
class FamilyService
{
    public function __construct(
        private TimetableService $timetableService,
        private FeeInvoiceService $invoiceService,
    ) {}

    /**
     * The signed-in user's own children (or the student themself) for the child switcher.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function children(User $user): Collection
    {
        return Student::whereIn('id', Student::ownIdsFor($user))
            ->with(['currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name'])
            ->orderBy('first_name')
            ->get(['id', 'admission_no', 'first_name', 'middle_name', 'last_name', 'current_status', 'photo_path'])
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->fullName(),
                'admission_no' => $student->admission_no,
                'status' => $student->current_status,
                'class' => trim(($student->currentEnrollment?->schoolClass?->name ?? '').' '.($student->currentEnrollment?->section?->name ?? '')) ?: null,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(Student $student): array
    {
        $enrollment = $student->currentEnrollment()->with(['schoolClass:id,name', 'section:id,name,class_teacher_id', 'section.classTeacher:id,first_name,last_name', 'academicSession:id,name'])->first();

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->fullName(),
                'admission_no' => $student->admission_no,
                'status' => $student->current_status,
                'class' => $enrollment?->schoolClass?->name,
                'section' => $enrollment?->section?->name,
                'roll_no' => $enrollment?->roll_no,
                'session' => $enrollment?->academicSession?->name,
                'class_teacher' => $enrollment?->section?->classTeacher?->fullName(),
                'leaving_certificate_url' => $student->slc_no ? route('erp.students.leaving-certificate.pdf', $student) : null,
            ],
            'timetable' => $enrollment?->section ? TimetableSlotResource::collection($this->timetableService->forSection($enrollment->section))->resolve() : [],
            'exams' => $enrollment ? $this->exams($student, $enrollment->class_id, $enrollment->academic_session_id) : [],
            'fees' => $this->fees($student),
            'leave_requests' => StudentLeaveRequest::where('student_id', $student->id)->latest('id')->limit(5)
                ->get(['id', 'from_date', 'to_date', 'reason', 'status', 'review_remarks'])
                ->map(fn (StudentLeaveRequest $leave): array => [
                    ...$leave->only(['id', 'reason', 'status', 'review_remarks']),
                    'from_date' => $leave->from_date?->toDateString(),
                    'to_date' => $leave->to_date?->toDateString(),
                ])->all(),
            'records' => collect(app(StudentCareService::class)->records($student, true))->only(['discipline', 'achievements'])->all(),
            'library' => app(LibraryService::class)->forBorrower('student', $student->id),
            'transport' => StudentTransport::where('student_id', $student->id)->where('status', 'active')
                ->with(['route:id,name', 'stop:id,name'])->get(['id', 'transport_route_id', 'transport_stop_id', 'from_date'])
                ->map(fn (StudentTransport $ride): array => ['route' => $ride->route?->name, 'stop' => $ride->stop?->name, 'from_date' => $ride->from_date?->toDateString()])
                ->all(),
        ];
    }

    /**
     * Exams of the class this session (not drafts): date sheet, the child's admit card and report card links.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exams(Student $student, int $classId, int $sessionId): array
    {
        $exams = Exam::where('class_id', $classId)
            ->whereHas('term', fn ($query) => $query->where('academic_session_id', $sessionId))
            ->where('status', '!=', 'draft')
            ->with(['term:id,name', 'subjects' => fn ($query) => $query->orderBy('exam_date')->orderBy('starts_at'), 'subjects.subject:id,name'])
            ->latest('id')
            ->get(['id', 'name', 'exam_term_id', 'status', 'published_at']);

        $cards = AdmitCard::where('student_id', $student->id)->whereIn('exam_id', $exams->pluck('id'))->get(['exam_id', 'exam_roll_no', 'is_eligible', 'ineligible_reason'])->keyBy('exam_id');

        return $exams->map(fn (Exam $exam): array => [
            'id' => $exam->id,
            'name' => $exam->name,
            'term' => $exam->term?->name,
            'status' => $exam->status,
            'date_sheet' => $exam->subjects->map(fn (ExamSubject $paper): array => [
                'subject' => $paper->subject?->name,
                'date' => $paper->exam_date?->toDateString(),
                'starts_at' => $paper->starts_at ? substr($paper->starts_at, 0, 5) : null,
                'duration_minutes' => $paper->duration_minutes,
            ])->all(),
            'admit_card' => ($card = $cards->get($exam->id)) ? [
                'exam_roll_no' => $card->exam_roll_no,
                'is_eligible' => (bool) $card->is_eligible,
                'reason' => $card->ineligible_reason,
                'pdf_url' => $card->is_eligible ? route('erp.exams.admit-cards.student-pdf', [$exam, $student]) : null,
            ] : null,
            'report_card_url' => $exam->status === 'published' ? route('erp.exams.report-cards.student-pdf', [$exam, $student]) : null,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fees(Student $student): array
    {
        $open = FeeInvoice::where('student_id', $student->id)->whereIn('status', FeeInvoice::OPEN_STATUSES)->where('balance', '>', 0)->orderBy('due_date')
            ->get(['id', 'invoice_no', 'invoice_type', 'billing_month', 'due_date', 'total', 'balance']);

        return [
            'due' => (int) $open->sum('balance'),
            'advance' => $this->invoiceService->advanceBalance($student->id),
            'open_challans' => $open->map(fn (FeeInvoice $invoice): array => [
                ...$invoice->only(['id', 'invoice_no', 'invoice_type', 'total', 'balance']),
                'billing_month' => $invoice->billing_month?->format('M Y'),
                'due_date' => $invoice->due_date?->toDateString(),
                'is_overdue' => $invoice->due_date?->isPast() && ! $invoice->due_date->isToday(),
                'pdf_url' => route('erp.fees.invoices.pdf', $invoice),
            ])->all(),
            'receipts' => FeePayment::where('student_id', $student->id)->latest('received_on')->latest('id')->limit(12)
                ->with('paymentMethod:id,name')->get(['id', 'receipt_no', 'payment_method_id', 'amount', 'received_on', 'status'])
                ->map(fn (FeePayment $payment): array => [
                    ...$payment->only(['id', 'receipt_no', 'amount', 'status']),
                    'received_on' => $payment->received_on?->toDateString(),
                    'method' => $payment->paymentMethod?->name,
                    'pdf_url' => route('erp.fees.payments.pdf', $payment),
                ])->all(),
        ];
    }
}
