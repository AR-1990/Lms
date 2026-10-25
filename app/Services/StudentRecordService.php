<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentStatusHistory;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Students, their guardians and their enrolment per session.
 */
class StudentRecordService
{
    public function __construct(
        private NumberSequenceService $numberSequenceService,
        private UserService $userService,
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Students with their enrolment in one session (the current one unless chosen).
     *
     * @param  array{academic_session_id?: int|null, class_id?: int|null, section_id?: int|null, status?: string|null, search?: string|null}  $filters
     */
    public function query(array $filters = []): Builder
    {
        $sessionId = $filters['academic_session_id'] ?? AcademicSession::where('is_current', true)->value('id');

        return Student::query()
            ->leftJoin('enrollments', fn (JoinClause $join) => $join->on('enrollments.student_id', '=', 'students.id')->where('enrollments.academic_session_id', '=', $sessionId))
            ->leftJoin('classes', 'classes.id', '=', 'enrollments.class_id')
            ->leftJoin('sections', 'sections.id', '=', 'enrollments.section_id')
            ->select([
                'students.id', 'students.admission_no', 'students.first_name', 'students.middle_name', 'students.last_name', 'students.gender_id',
                'students.date_of_birth', 'students.current_status', 'students.phone', 'students.admission_date', 'students.b_form_no',
                'enrollments.id as enrollment_id', 'enrollments.class_id', 'enrollments.section_id', 'enrollments.roll_no',
                'classes.name as class_name', 'classes.level_order', 'sections.name as section_name',
            ])
            ->when($filters['class_id'] ?? null, fn (Builder $query, int $id) => $query->where('enrollments.class_id', $id))
            ->when($filters['section_id'] ?? null, fn (Builder $query, int $id) => $query->where('enrollments.section_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('students.current_status', $status))
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('students.first_name', 'like', '%'.$filters['search'].'%')
                ->orWhere('students.last_name', 'like', '%'.$filters['search'].'%')
                ->orWhere('students.admission_no', 'like', '%'.$filters['search'].'%')
                ->orWhere('students.b_form_no', 'like', '%'.$filters['search'].'%')));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function datatable(array $filters): array
    {
        return DataTables::eloquent($this->query(collect($filters)->except('search')->all()))
            ->escapeColumns([])
            ->addColumn('full_name', fn (Student $student): string => $student->fullName())
            ->filterColumn('full_name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $inner) => $inner
                ->where('students.first_name', 'like', "%{$keyword}%")
                ->orWhere('students.last_name', 'like', "%{$keyword}%")))
            ->filterColumn('class_name', fn (Builder $query, string $keyword) => $query->where('classes.name', 'like', "%{$keyword}%"))
            ->orderColumn('full_name', 'students.first_name $1, students.last_name $1')
            ->orderColumn('class_name', 'classes.level_order $1, sections.name $1, enrollments.roll_no $1')
            ->editColumn('date_of_birth', fn (Student $student): ?string => $student->date_of_birth?->toDateString())
            ->editColumn('admission_date', fn (Student $student): ?string => $student->admission_date?->toDateString())
            ->toArray();
    }

    /**
     * One row per session the student studied in: class, section, how the year ended,
     * published results, attendance and the year's fees.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(Student $student): array
    {
        $enrollments = $student->enrollments()
            ->with(['academicSession:id,name,starts_on,is_current', 'schoolClass:id,name', 'section:id,name'])
            ->get(['id', 'student_id', 'academic_session_id', 'class_id', 'section_id', 'roll_no', 'enrolled_on', 'status', 'result_status'])
            ->sortByDesc(fn (Enrollment $enrollment) => $enrollment->academicSession?->starts_on)
            ->values();
        $ids = $enrollments->pluck('id');

        $results = ReportCard::with(['exam:id,name,exam_term_id', 'exam.term:id,name,is_final'])
            ->whereIn('enrollment_id', $ids)
            ->whereHas('exam', fn (Builder $exam) => $exam->whereNotNull('published_at'))
            ->orderBy('id')
            ->get(['id', 'exam_id', 'enrollment_id', 'percentage', 'grade', 'result', 'position_in_class'])
            ->groupBy('enrollment_id');

        $attendance = StudentAttendance::whereIn('enrollment_id', $ids)
            ->selectRaw('enrollment_id, status, count(*) as total')
            ->groupBy('enrollment_id', 'status')
            ->get()
            ->groupBy('enrollment_id');

        $fees = FeeInvoice::where('student_id', $student->id)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('academic_session_id, count(*) as challans, sum(total) as billed, sum(paid_total) as paid, sum(waived_total) as waived, sum(balance) as balance')
            ->groupBy('academic_session_id')
            ->get()
            ->keyBy('academic_session_id');

        return $enrollments->map(function (Enrollment $enrollment) use ($results, $attendance, $fees): array {
            $counts = ($attendance[$enrollment->id] ?? collect())->pluck('total', 'status')->map(fn ($total): int => (int) $total)->all();
            $fee = $fees[$enrollment->academic_session_id] ?? null;

            return [
                'enrollment_id' => $enrollment->id,
                'session' => $enrollment->academicSession?->only(['id', 'name', 'is_current']),
                'class' => $enrollment->schoolClass?->name,
                'section' => $enrollment->section?->name,
                'roll_no' => $enrollment->roll_no,
                'enrolled_on' => $enrollment->enrolled_on?->toDateString(),
                'status' => $enrollment->status,
                'result_status' => $enrollment->result_status,
                'results' => ($results[$enrollment->id] ?? collect())->map(fn (ReportCard $card): array => [
                    'exam' => $card->exam?->name,
                    'term' => $card->exam?->term?->name,
                    'is_final' => (bool) $card->exam?->term?->is_final,
                    'percentage' => $card->percentage,
                    'grade' => $card->grade,
                    'result' => $card->result,
                    'position_in_class' => $card->position_in_class,
                ])->values()->all(),
                'attendance' => ['days' => array_sum($counts), 'percentage' => app(AttendanceService::class)->percentage($counts), 'counts' => $counts],
                'fees' => [
                    'challans' => (int) ($fee->challans ?? 0),
                    'billed' => (int) ($fee->billed ?? 0),
                    'paid' => (int) ($fee->paid ?? 0),
                    'waived' => (int) ($fee->waived ?? 0),
                    'balance' => (int) ($fee->balance ?? 0),
                ],
            ];
        })->all();
    }

    public function find(Student $student): Student
    {
        return $student->load([
            'gender:id,name', 'user:id,username,email,is_active', 'guardians.user:id,phone,is_active',
            'enrollments.academicSession:id,name', 'enrollments.schoolClass:id,name', 'enrollments.section:id,name',
            'statusHistories.changedBy:id,name', 'statusHistories.reason:id,name',
        ]);
    }

    /**
     * Create a student with an admission number, an enrolment and guardians (new or existing).
     *
     * @param  array<string, mixed>  $data  student fields + enrollment{} + guardians[]
     */
    public function create(array $data): Student
    {
        return DB::transaction(function () use ($data): Student {
            $student = Student::create([
                ...collect($data)->except(['enrollment', 'guardians', 'admission_no'])->all(),
                'admission_no' => filled($data['admission_no'] ?? null) ? $data['admission_no'] : $this->numberSequenceService->next('admission_no'),
                'current_status' => 'active',
            ]);

            $this->checkSection($data['enrollment']);

            Enrollment::create([
                ...$data['enrollment'],
                'student_id' => $student->id,
                'enrolled_on' => $data['enrollment']['enrolled_on'] ?? $student->admission_date->toDateString(),
                'status' => 'active',
            ]);

            foreach ($data['guardians'] as $guardianData) {
                $this->attachGuardian($student, $guardianData);
            }

            $this->ensureOnePrimary($student);

            StudentStatusHistory::create([
                'student_id' => $student->id,
                'from_status' => null,
                'to_status' => 'active',
                'effective_on' => $student->admission_date->toDateString(),
                'remarks' => 'Admitted',
                'changed_by' => auth()->id(),
            ]);

            return $this->find($student);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data): Student {
            $student->update(collect($data)->except(['enrollment', 'guardians', 'admission_no', 'current_status'])->all());

            if ($student->user_id !== null) {
                $student->user()->first()?->update(['name' => $student->fullName()]);
            }

            return $this->find($student);
        });
    }

    /**
     * Move a student to another class / section or change the roll number for the session.
     *
     * @param  array{class_id: int, section_id?: int|null, roll_no?: string|null}  $data
     */
    public function updateEnrollment(Enrollment $enrollment, array $data): Enrollment
    {
        $this->checkSection([...$data, 'academic_session_id' => $enrollment->academic_session_id]);

        return DB::transaction(function () use ($enrollment, $data): Enrollment {
            $enrollment->update($data);

            return $enrollment->load(['schoolClass:id,name', 'section:id,name', 'academicSession:id,name']);
        });
    }

    /**
     * Suspend, withdraw, graduate… Leaving statuses close the enrolment and switch off the student's login.
     *
     * @throws ValidationException when the status does not change
     */
    public function changeStatus(Student $student, string $status, string $effectiveOn, ?int $reasonId, ?string $remarks): Student
    {
        if ($student->current_status === $status) {
            throw ValidationException::withMessages(['status' => ['The student is already '.$status.'.']]);
        }

        return DB::transaction(function () use ($student, $status, $effectiveOn, $reasonId, $remarks): Student {
            $old = $student->current_status;
            $isLeaving = in_array($status, Student::LEAVING_STATUSES, true);

            $student->update([
                'current_status' => $status,
                'left_on' => $isLeaving ? $effectiveOn : null,
                'leaving_reason_id' => $isLeaving ? $reasonId : null,
            ]);

            StudentStatusHistory::create([
                'student_id' => $student->id,
                'from_status' => $old,
                'to_status' => $status,
                'reason_id' => $reasonId,
                'effective_on' => $effectiveOn,
                'remarks' => $remarks,
                'changed_by' => auth()->id(),
            ]);

            $student->currentEnrollment()->first()?->update(['status' => $isLeaving ? 'withdrawn' : 'active']);

            if ($isLeaving) {
                app(TransportService::class)->endForStudent($student, $effectiveOn);
            }

            if ($student->user_id !== null) {
                $user = $student->user()->first();
                $user?->update(['is_active' => ! $isLeaving]);

                if ($isLeaving && $user !== null) {
                    $user->tokens()->delete();
                    UserDevice::where('user_id', $user->id)->update(['is_active' => false]);
                }
            }

            return $this->find($student);
        });
    }

    /**
     * @throws ValidationException when fee records exist (withdraw the student instead)
     */
    public function delete(Student $student): void
    {
        if (FeeInvoice::where('student_id', $student->id)->exists() || FeePayment::where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages(['student' => ['This student has fee records. Change the status to withdrawn or left instead.']]);
        }

        DB::transaction(function () use ($student): void {
            Enrollment::where('student_id', $student->id)->get()->each(fn (Enrollment $enrollment) => $enrollment->delete());
            $this->unlinkGuardians($student);

            if ($student->user_id !== null) {
                $student->user()->first()?->update(['is_active' => false]);
            }

            $student->delete();
        });
    }

    /* ---------- Guardians ---------- */

    /**
     * Link a guardian: an existing one (by id, CNIC or phone + name) or a new one.
     *
     * @param  array<string, mixed>  $data  guardian fields + relation_id, is_primary_contact, is_fee_payer…
     */
    public function attachGuardian(Student $student, array $data): Guardian
    {
        $guardian = $this->resolveGuardian($data);

        if ($student->guardians()->whereKey($guardian->id)->exists()) {
            throw ValidationException::withMessages(['guardian' => ["{$guardian->name} is already linked to this student."]]);
        }

        $pivot = [
            'relation_id' => $data['relation_id'],
            'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
            'is_fee_payer' => (bool) ($data['is_fee_payer'] ?? false),
            'can_pick_up' => (bool) ($data['can_pick_up'] ?? true),
            'lives_with_student' => (bool) ($data['lives_with_student'] ?? true),
        ];

        if ($pivot['is_primary_contact']) {
            DB::table('guardian_student')->where('student_id', $student->id)->whereNull('deleted_at')->update(['is_primary_contact' => false]);
        }

        $student->guardians()->attach($guardian->id, $pivot);

        $this->activityLogger->record('students', 'guardian_linked', (auth()->user()?->name ?? 'System')." linked {$guardian->name} to {$student->fullName()}", $student);

        return $guardian;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGuardian(Guardian $guardian, array $data): Guardian
    {
        return DB::transaction(function () use ($guardian, $data): Guardian {
            $guardian->update(collect($data)->except(['relation_id', 'is_primary_contact', 'is_fee_payer', 'can_pick_up', 'lives_with_student', 'guardian_id'])->all());

            return $guardian;
        });
    }

    /**
     * @throws ValidationException when it is the student's only guardian
     */
    public function detachGuardian(Student $student, Guardian $guardian): void
    {
        if ($student->guardians()->count() <= 1) {
            throw ValidationException::withMessages(['guardian' => ['A student needs at least one guardian.']]);
        }

        DB::transaction(function () use ($student, $guardian): void {
            $this->unlinkGuardians($student, $guardian->id);
            $this->ensureOnePrimary($student);
            $this->activityLogger->record('students', 'guardian_unlinked', (auth()->user()?->name ?? 'System')." unlinked {$guardian->name} from {$student->fullName()}", $student);
        });
    }

    /**
     * Parent portal login: the guardian signs in with their phone number. One login serves all their children.
     *
     * @throws ValidationException when the guardian already has a login or the phone is taken
     */
    public function createParentLogin(Guardian $guardian, string $password): Guardian
    {
        if ($guardian->user_id !== null) {
            throw ValidationException::withMessages(['guardian' => ['This guardian already has a portal login.']]);
        }

        $phone = preg_replace('/[\s-]/', '', $guardian->phone);

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => ['Another login already uses this phone number.']]);
        }

        return DB::transaction(function () use ($guardian, $phone, $password): Guardian {
            $user = $this->userService->createUser([
                'name' => $guardian->name,
                'email' => $guardian->email ?: 'guardian-'.$guardian->id.'@parents.invalid',
                'phone' => $phone,
                'password' => $password,
            ], ['parent']);

            $guardian->forceFill(['user_id' => $user->id])->saveQuietly();
            $this->activityLogger->record('guardians', 'portal_account_created', (auth()->user()?->name ?? 'System')." created a parent login for {$guardian->name}", $guardian);

            return $guardian->load('user:id,phone,is_active');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveGuardian(array $data): Guardian
    {
        if (filled($data['guardian_id'] ?? null)) {
            return Guardian::findOrFail($data['guardian_id']);
        }

        $existing = filled($data['cnic'] ?? null)
            ? Guardian::where('cnic', $data['cnic'])->first()
            : Guardian::where('phone', $data['phone'] ?? '')->where('name', $data['name'] ?? '')->first();

        return $existing ?? Guardian::create(collect($data)->only([
            'name', 'cnic', 'phone', 'alt_phone', 'whatsapp', 'email', 'occupation_id', 'employer', 'designation', 'monthly_income', 'education_id', 'address_line', 'city',
        ])->all());
    }

    private function ensureOnePrimary(Student $student): void
    {
        $hasPrimary = DB::table('guardian_student')->where('student_id', $student->id)->whereNull('deleted_at')->where('is_primary_contact', true)->exists();

        if (! $hasPrimary) {
            DB::table('guardian_student')->where('student_id', $student->id)->whereNull('deleted_at')->orderBy('id')->limit(1)->update(['is_primary_contact' => true]);
        }
    }

    /**
     * End guardian links by soft-deleting the pivot rows (one guardian, or all of them).
     */
    private function unlinkGuardians(Student $student, ?int $guardianId = null): void
    {
        DB::table('guardian_student')
            ->where('student_id', $student->id)
            ->when($guardianId, fn ($query, int $id) => $query->where('guardian_id', $id))
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $enrollment
     *
     * @throws ValidationException when the section is not of that class and session
     */
    private function checkSection(array $enrollment): void
    {
        if (blank($enrollment['section_id'] ?? null)) {
            return;
        }

        $fits = Section::whereKey($enrollment['section_id'])
            ->where('class_id', $enrollment['class_id'])
            ->where('academic_session_id', $enrollment['academic_session_id'])
            ->exists();

        if (! $fits) {
            throw ValidationException::withMessages(['enrollment.section_id' => ['This section belongs to another class or session.']]);
        }
    }
}
