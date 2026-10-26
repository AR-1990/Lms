<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use App\Models\AdmissionApplicationGuardian;
use App\Models\AdmissionInquiry;
use App\Models\AdmissionInquiryFollowUp;
use App\Models\AdmissionInterview;
use App\Models\AdmissionStatusHistory;
use App\Models\AdmissionTest;
use App\Models\CardTemplate;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Inquiry → application (+ registration challan) → documents / test / interview → offer
 * → accepted (+ admission challan) → enrolled (student, guardians, enrolment, logins).
 */
class AdmissionService
{
    /**
     * Statuses in which the application can no longer change.
     *
     * @var array<int, string>
     */
    private const CLOSED = ['enrolled', 'rejected', 'withdrawn'];

    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private FeeInvoiceService $invoiceService,
        private StudentRecordService $studentService,
        private NotificationService $notificationService,
    ) {}

    /* ---------- Inquiries ---------- */

    /**
     * @param  array{status?: string|null}  $filters
     */
    public function inquiriesQuery(array $filters = []): Builder
    {
        return AdmissionInquiry::query()
            ->with(['classSought:id,name', 'source:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * Contacts with the family, newest first.
     *
     * @return Collection<int, AdmissionInquiryFollowUp>
     */
    public function followUps(AdmissionInquiry $inquiry): Collection
    {
        return AdmissionInquiryFollowUp::where('admission_inquiry_id', $inquiry->id)->with(['channel:id,name', 'creator:id,name'])->latest('followed_up_on')->latest('id')->get();
    }

    /**
     * Log a call / visit / message; the enquiry's next follow-up date moves to the one given (empty = none),
     * and a new enquiry becomes "contacted".
     *
     * @param  array{followed_up_on: string, channel_id?: int|null, outcome: string, next_follow_up_on?: string|null}  $data
     */
    public function addFollowUp(AdmissionInquiry $inquiry, array $data): AdmissionInquiryFollowUp
    {
        if (in_array($inquiry->status, ['converted', 'closed'], true)) {
            throw ValidationException::withMessages(['outcome' => ['This enquiry is '.$inquiry->status.'.']]);
        }

        return DB::transaction(function () use ($inquiry, $data): AdmissionInquiryFollowUp {
            $followUp = AdmissionInquiryFollowUp::create([...$data, 'admission_inquiry_id' => $inquiry->id]);
            $inquiry->update(['follow_up_on' => $data['next_follow_up_on'] ?? null, 'status' => $inquiry->status === 'new' ? 'contacted' : $inquiry->status]);

            return $followUp->load(['channel:id,name', 'creator:id,name']);
        });
    }

    /**
     * Daily: everyone who handles admissions (or the enquiry's assigned person) hears which open enquiries
     * are due or overdue for a follow-up today. Returns how many enquiries are due.
     */
    public function followUpReminders(): int
    {
        $due = AdmissionInquiry::whereNotIn('status', ['converted', 'closed'])->whereNotNull('follow_up_on')->whereDate('follow_up_on', '<=', today())->get(['id', 'parent_name', 'child_name', 'follow_up_on', 'assigned_to']);

        if ($due->isEmpty()) {
            return 0;
        }

        $staff = User::where('is_active', true)->whereHas('roles.permissions', fn (Builder $query) => $query->where('slug', 'manage-admissions'))->pluck('id');
        $overdue = $due->filter(fn (AdmissionInquiry $inquiry): bool => $inquiry->follow_up_on->isPast() && ! $inquiry->follow_up_on->isToday())->count();
        $names = $due->take(5)->map(fn (AdmissionInquiry $inquiry): string => $inquiry->parent_name.($inquiry->child_name ? ' ('.$inquiry->child_name.')' : ''))->implode(', ');

        $this->notificationService->notify(
            $staff->merge($due->pluck('assigned_to')->filter())->unique()->values()->all(), 'inquiry_follow_ups_due',
            $due->count().' admission enquir'.($due->count() === 1 ? 'y' : 'ies').' to follow up today',
            $names.($due->count() > 5 ? ' and '.($due->count() - 5).' more' : '').($overdue ? " · {$overdue} overdue" : ''),
            route('admin.admissions.inquiries'), null, $overdue > 0,
        );

        return $due->count();
    }

    /**
     * @param  array{status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function inquiriesDatatable(array $filters): array
    {
        return DataTables::eloquent($this->inquiriesQuery($filters)->withCount('followUps'))
            ->escapeColumns([])
            ->addColumn('class_name', fn (AdmissionInquiry $inquiry): ?string => $inquiry->classSought?->name)
            ->addColumn('source_name', fn (AdmissionInquiry $inquiry): ?string => $inquiry->source?->name)
            ->editColumn('inquiry_date', fn (AdmissionInquiry $inquiry): string => $inquiry->inquiry_date->toDateString())
            ->editColumn('follow_up_on', fn (AdmissionInquiry $inquiry): ?string => $inquiry->follow_up_on?->toDateString())
            ->editColumn('child_dob', fn (AdmissionInquiry $inquiry): ?string => $inquiry->child_dob?->toDateString())
            ->removeColumn('class_sought', 'source')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveInquiry(?AdmissionInquiry $inquiry, array $data): AdmissionInquiry
    {
        if ($inquiry !== null && $inquiry->status === 'converted') {
            throw ValidationException::withMessages(['inquiry' => ['This inquiry is already converted. Edit its application instead.']]);
        }

        return DB::transaction(function () use ($inquiry, $data): AdmissionInquiry {
            if ($inquiry === null) {
                $inquiry = AdmissionInquiry::create([
                    ...$data,
                    'inquiry_no' => $this->numberSequenceService->next('inquiry_no'),
                    'inquiry_date' => $data['inquiry_date'] ?? now()->toDateString(),
                    'status' => $data['status'] ?? 'new',
                ]);
            } else {
                $inquiry->update($data);
            }

            return $inquiry->load(['classSought:id,name', 'source:id,name']);
        });
    }

    public function deleteInquiry(AdmissionInquiry $inquiry): void
    {
        if ($inquiry->status === 'converted') {
            throw ValidationException::withMessages(['inquiry' => ['A converted inquiry is part of an application and cannot be deleted.']]);
        }

        DB::transaction(fn () => $inquiry->delete());
    }

    /**
     * Turn an inquiry into an application; the inquiry is marked converted.
     *
     * @param  array<string, mixed>  $data  application fields (guardians included)
     */
    public function convertInquiry(AdmissionInquiry $inquiry, array $data): AdmissionApplication
    {
        if ($inquiry->status === 'converted' || $inquiry->status === 'closed') {
            throw ValidationException::withMessages(['inquiry' => ['Only an open inquiry can be converted.']]);
        }

        return DB::transaction(function () use ($inquiry, $data): AdmissionApplication {
            $application = $this->createApplication([...$data, 'inquiry_id' => $inquiry->id]);
            $inquiry->update(['status' => 'converted', 'application_id' => $application->id]);

            return $application;
        });
    }

    /* ---------- Applications ---------- */

    /**
     * @param  array{academic_session_id?: int|null, class_id?: int|null, status?: string|null}  $filters
     */
    public function applicationsQuery(array $filters = []): Builder
    {
        return AdmissionApplication::query()
            ->select(['id', 'application_no', 'academic_session_id', 'class_applied_id', 'applied_on', 'first_name', 'middle_name', 'last_name', 'gender_id', 'date_of_birth', 'status', 'student_id', 'created_at'])
            ->with(['classApplied:id,name', 'academicSession:id,name'])
            ->withSum(['invoices as fees_due' => fn (Builder $query) => $query->whereIn('status', FeeInvoice::OPEN_STATUSES)], 'balance')
            ->when($filters['academic_session_id'] ?? null, fn (Builder $query, int $id) => $query->where('academic_session_id', $id))
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $id) => $query->where('class_applied_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function applicationsDatatable(array $filters): array
    {
        return DataTables::eloquent($this->applicationsQuery($filters))
            ->escapeColumns([])
            ->addColumn('full_name', fn (AdmissionApplication $application): string => $application->fullName())
            ->addColumn('class_name', fn (AdmissionApplication $application): ?string => $application->classApplied?->name)
            ->addColumn('session_name', fn (AdmissionApplication $application): ?string => $application->academicSession?->name)
            ->addColumn('next_statuses', fn (AdmissionApplication $application): array => AdmissionApplication::TRANSITIONS[$application->status] ?? [])
            ->editColumn('applied_on', fn (AdmissionApplication $application): string => $application->applied_on->toDateString())
            ->editColumn('date_of_birth', fn (AdmissionApplication $application): string => $application->date_of_birth->toDateString())
            ->filterColumn('full_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")))
            ->orderColumn('full_name', 'first_name $1, last_name $1')
            ->removeColumn('class_applied', 'academic_session')
            ->toArray();
    }

    public function findApplication(AdmissionApplication $application): AdmissionApplication
    {
        return $application->load([
            'classApplied:id,name', 'academicSession:id,name', 'gender:id,name', 'guardians.relation:id,name', 'tests.subject:id,name',
            'interviews', 'statusHistories.changedBy:id,name', 'invoices:id,admission_application_id,invoice_no,invoice_type,total,paid_total,waived_total,balance,status,due_date',
            'student:id,admission_no',
        ]);
    }

    /**
     * Submit an application with its guardians; the registration challan is raised when the class has one.
     *
     * @param  array<string, mixed>  $data
     */
    public function createApplication(array $data): AdmissionApplication
    {
        return DB::transaction(function () use ($data): AdmissionApplication {
            $application = AdmissionApplication::create([
                ...collect($data)->except('guardians')->all(),
                'application_no' => $this->numberSequenceService->next('application_no'),
                'applied_on' => $data['applied_on'] ?? now()->toDateString(),
                'status' => 'submitted',
            ]);

            $this->saveGuardians($application, $data['guardians']);
            $this->history($application, null, 'submitted', 'Application submitted');
            $this->invoiceService->forApplication($application, 'on_application');

            return $this->findApplication($application);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateApplication(AdmissionApplication $application, array $data): AdmissionApplication
    {
        $this->ensureOpen($application);

        return DB::transaction(function () use ($application, $data): AdmissionApplication {
            $application->update(collect($data)->except(['guardians', 'academic_session_id', 'class_applied_id', 'status'])->all());

            if (array_key_exists('guardians', $data)) {
                $application->guardians()->delete();
                $this->saveGuardians($application, $data['guardians']);
            }

            return $this->findApplication($application);
        });
    }

    /**
     * Move the application to its next step. Accepting the offer raises the admission challan.
     *
     * @param  array{status: string, remarks?: string|null, rejection_reason_id?: int|null, offer_expires_on?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function changeStatus(AdmissionApplication $application, array $data): AdmissionApplication
    {
        $status = $data['status'];
        $allowed = AdmissionApplication::TRANSITIONS[$application->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => [
                $allowed === [] ? 'This application is closed ('.$application->status.').' : 'From '.str_replace('_', ' ', $application->status).' the next step can be: '.implode(', ', $allowed).'.',
            ]]);
        }

        return DB::transaction(function () use ($application, $data, $status): AdmissionApplication {
            $old = $application->status;
            $isDecision = in_array($status, ['offered', 'waitlisted', 'rejected'], true);

            $application->update([
                'status' => $status,
                'decision_on' => $isDecision ? now()->toDateString() : $application->decision_on,
                'decision_by' => $isDecision ? auth()->id() : $application->decision_by,
                'rejection_reason_id' => $status === 'rejected' ? ($data['rejection_reason_id'] ?? null) : $application->rejection_reason_id,
                'offer_expires_on' => $status === 'offered' ? ($data['offer_expires_on'] ?? null) : $application->offer_expires_on,
            ]);

            $this->history($application, $old, $status, $data['remarks'] ?? null);

            if ($status === 'accepted' && ! $application->invoices()->where('invoice_type', 'admission')->where('status', '!=', 'cancelled')->exists()) {
                $this->invoiceService->forApplication($application, 'on_admission');
            }

            return $this->findApplication($application);
        });
    }

    /**
     * Withdrawn or rejected applications with no money received can be removed; their challans are cancelled.
     *
     * @throws ValidationException
     */
    public function deleteApplication(AdmissionApplication $application): void
    {
        if ($application->status === 'enrolled' || FeePayment::where('admission_application_id', $application->id)->where('status', 'posted')->exists()) {
            throw ValidationException::withMessages(['application' => ['Money was received or the child is enrolled. Withdraw the application instead.']]);
        }

        DB::transaction(function () use ($application): void {
            $application->invoices()->whereIn('status', FeeInvoice::OPEN_STATUSES)->get()
                ->each(fn (FeeInvoice $invoice) => $this->invoiceService->cancel($invoice, 'Application deleted'));
            $application->delete();
        });
    }

    /* ---------- Entry tests & interviews ---------- */

    /**
     * @param  array<string, mixed>  $data
     */
    public function addTest(AdmissionApplication $application, array $data): AdmissionTest
    {
        $this->ensureOpen($application);

        return DB::transaction(function () use ($application, $data): AdmissionTest {
            $test = AdmissionTest::create([...$data, 'application_id' => $application->id]);
            $this->moveTo($application, ['submitted', 'documents_pending', 'documents_verified'], 'test_scheduled', 'Entry test scheduled');

            return $test->load('subject:id,name');
        });
    }

    /**
     * Recording the last result moves the application to "test done".
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTest(AdmissionTest $test, array $data): AdmissionTest
    {
        return DB::transaction(function () use ($test, $data): AdmissionTest {
            $test->update($data);
            $application = AdmissionApplication::findOrFail($test->application_id);

            if (! $application->tests()->whereNull('result')->exists()) {
                $this->moveTo($application, ['test_scheduled'], 'test_done', 'All entry test results recorded');
            }

            return $test->load('subject:id,name');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addInterview(AdmissionApplication $application, array $data): AdmissionInterview
    {
        $this->ensureOpen($application);

        return DB::transaction(function () use ($application, $data): AdmissionInterview {
            $interview = AdmissionInterview::create([...$data, 'application_id' => $application->id, 'mode' => $data['mode'] ?? 'in_person']);
            $this->moveTo($application, ['submitted', 'documents_pending', 'documents_verified', 'test_done'], 'interview_scheduled', 'Interview scheduled');

            return $interview;
        });
    }

    /**
     * Marking the interview as attended moves the application to "interviewed".
     *
     * @param  array<string, mixed>  $data
     */
    public function updateInterview(AdmissionInterview $interview, array $data): AdmissionInterview
    {
        return DB::transaction(function () use ($interview, $data): AdmissionInterview {
            $interview->update($data);

            if ($interview->attended) {
                $this->moveTo(AdmissionApplication::findOrFail($interview->application_id), ['interview_scheduled'], 'interviewed', 'Interview held');
            }

            return $interview;
        });
    }

    /* ---------- Enrolment ---------- */

    /**
     * Create the student from an accepted application. The admission challan must be cleared first
     * (paid or waived). Challans and receipts of the applicant move to the new student.
     *
     * @param  array{admission_date?: string|null, section_id?: int|null, roll_no?: string|null, parent_password?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function enrol(AdmissionApplication $application, array $data): Student
    {
        if ($application->status !== 'accepted') {
            throw ValidationException::withMessages(['status' => ['Only an accepted offer can be enrolled.']]);
        }

        $unpaid = (int) $application->invoices()->where('invoice_type', 'admission')->whereIn('status', FeeInvoice::OPEN_STATUSES)->sum('balance');

        if ($unpaid > 0) {
            throw ValidationException::withMessages(['fees' => ['The admission fee of Rs '.number_format($unpaid / 100, 2).' is unpaid. Receive it or waive it first.']]);
        }

        return DB::transaction(function () use ($application, $data): Student {
            $application->load('guardians');

            $student = $this->studentService->create([
                ...$application->only(['campus_id', 'first_name', 'middle_name', 'last_name', 'gender_id', 'place_of_birth', 'b_form_no', 'nationality_id', 'religion_id', 'blood_group_id', 'photo_path', 'address_line', 'city', 'postal_code', 'previous_school', 'previous_class', 'special_needs']),
                'date_of_birth' => $application->date_of_birth->toDateString(),
                'admission_date' => $data['admission_date'] ?? now()->toDateString(),
                'application_id' => $application->id,
                'enrollment' => [
                    'academic_session_id' => $application->academic_session_id,
                    'class_id' => $application->class_applied_id,
                    'section_id' => $data['section_id'] ?? null,
                    'roll_no' => $data['roll_no'] ?? null,
                ],
                'guardians' => $application->guardians->map(fn (AdmissionApplicationGuardian $guardian): array => [
                    ...$guardian->only(['name', 'cnic', 'phone', 'email', 'occupation_id', 'employer', 'monthly_income', 'relation_id', 'is_primary_contact']),
                    'guardian_id' => $guardian->existing_guardian_id,
                    'is_fee_payer' => $guardian->is_primary_contact,
                ])->all(),
            ]);

            $enrollmentId = $student->enrollments->first()?->id;
            FeeInvoice::where('admission_application_id', $application->id)->update(['student_id' => $student->id, 'enrollment_id' => $enrollmentId]);
            FeePayment::where('admission_application_id', $application->id)->update(['student_id' => $student->id]);

            $application->update(['status' => 'enrolled', 'student_id' => $student->id]);
            $this->history($application, 'accepted', 'enrolled', 'Enrolled as '.$student->admission_no);

            if (filled($data['parent_password'] ?? null)) {
                $primary = $student->guardians()->wherePivot('is_primary_contact', true)->first();

                if ($primary !== null && $primary->user_id === null) {
                    $this->studentService->createParentLogin($primary, $data['parent_password']);
                }
            }

            if (filled($data['student_password'] ?? null)) {
                $this->studentService->createStudentLogin($student, $data['student_password']);
            }

            // This month's tuition challan, so the family does not wait for the next monthly run.
            if (($data['issue_first_challan'] ?? true) && $enrollmentId !== null) {
                $this->invoiceService->monthly(Enrollment::with(['student', 'academicSession'])->findOrFail($enrollmentId), now());
            }

            if (($data['issue_id_card'] ?? true) && CardTemplate::where('card_type', 'student')->where('is_active', true)->exists()) {
                app(CardService::class)->issue(['holder_type' => 'student', 'holder_ids' => [$student->id]]);
            }

            return $this->studentService->find($student);
        });
    }

    /* ---------- Internals ---------- */

    /**
     * @param  array<int, array<string, mixed>>  $guardians
     */
    private function saveGuardians(AdmissionApplication $application, array $guardians): void
    {
        $hasPrimary = collect($guardians)->contains(fn (array $guardian): bool => (bool) ($guardian['is_primary_contact'] ?? false));

        foreach (array_values($guardians) as $index => $guardian) {
            $application->guardians()->create([
                ...$guardian,
                'is_primary_contact' => $hasPrimary ? (bool) ($guardian['is_primary_contact'] ?? false) : $index === 0,
            ]);
        }
    }

    /**
     * @param  array<int, string>  $from
     */
    private function moveTo(AdmissionApplication $application, array $from, string $to, string $remarks): void
    {
        if (! in_array($application->status, $from, true)) {
            return;
        }

        $old = $application->status;
        $application->update(['status' => $to]);
        $this->history($application, $old, $to, $remarks);
    }

    /**
     * @throws ValidationException
     */
    private function ensureOpen(AdmissionApplication $application): void
    {
        if (in_array($application->status, self::CLOSED, true)) {
            throw ValidationException::withMessages(['application' => ['This application is '.$application->status.' and can no longer change.']]);
        }
    }

    private function history(AdmissionApplication $application, ?string $from, string $to, ?string $remarks): void
    {
        AdmissionStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'changed_by' => auth()->id(),
        ]);
    }
}
