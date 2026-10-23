<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\IdCard;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentLeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

/**
 * Daily student attendance by section, staff attendance, monthly summaries and student leave requests.
 * Teachers mark only sections they teach or are class teacher of; managers mark any.
 */
class AttendanceService
{
    public function __construct(
        private CalendarService $calendarService,
        private TeacherAssignmentService $assignmentService,
        private NotificationService $notificationService,
    ) {}

    /**
     * @throws ValidationException when the user does not teach the section
     */
    public function ensureCanMark(User $user, Section $section): void
    {
        if (! $user->hasPermission('manage-students') && ! $this->assignmentService->teaches($user, $section)) {
            throw ValidationException::withMessages(['section_id' => ['You can mark attendance only for sections you teach.']]);
        }
    }

    /**
     * The register for a section on a day: saved marks, or "leave" for approved leave, else unmarked.
     *
     * @return array{date: string, holiday: string|null, is_sunday: bool, marked: bool, rows: array<int, array<string, mixed>>}
     */
    public function sheet(Section $section, string $date): array
    {
        $enrollments = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->whereHas('student', fn (Builder $query) => $query->where('current_status', 'active'))
            ->with('student:id,first_name,last_name,admission_no')
            ->get(['id', 'student_id', 'roll_no'])
            ->sortBy(fn (Enrollment $enrollment): string => str_pad((string) $enrollment->roll_no, 6, '0', STR_PAD_LEFT).$enrollment->student->first_name)
            ->values();

        $saved = StudentAttendance::where('section_id', $section->id)->whereDate('date', $date)->get()->keyBy('enrollment_id');
        $onLeave = StudentLeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)
            ->whereIn('student_id', $enrollments->pluck('student_id'))->pluck('student_id')->all();

        return [
            'date' => $date,
            'holiday' => $this->calendarService->holidayOn($date)?->title,
            'is_sunday' => Carbon::parse($date)->isSunday(),
            'marked' => $saved->isNotEmpty(),
            'rows' => $enrollments->map(fn (Enrollment $enrollment): array => [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'name' => $enrollment->student->fullName(),
                'admission_no' => $enrollment->student->admission_no,
                'roll_no' => $enrollment->roll_no,
                'status' => $saved->get($enrollment->id)?->status ?? (in_array($enrollment->student_id, $onLeave, true) ? 'leave' : null),
                'remarks' => $saved->get($enrollment->id)?->remarks,
                'on_leave' => in_array($enrollment->student_id, $onLeave, true),
            ])->all(),
        ];
    }

    /**
     * Save (or correct) a day's register. Only changed rows are written, so the log shows real changes.
     *
     * @param  array<int, array{enrollment_id: int, status: string, remarks?: string|null}>  $rows
     * @return array<string, int> counts per status
     *
     * @throws ValidationException for future dates, holidays or students outside the section
     */
    public function mark(Section $section, string $date, array $rows, User $user): array
    {
        $this->ensureCanMark($user, $section);
        $this->ensureSchoolDay($date);

        $enrollments = Enrollment::where('section_id', $section->id)->where('status', 'active')->pluck('student_id', 'id');

        foreach ($rows as $row) {
            if (! $enrollments->has($row['enrollment_id'])) {
                throw ValidationException::withMessages(['rows' => ['A student in the list is not in this section.']]);
            }
        }

        $newlyAbsent = [];

        DB::transaction(function () use ($section, $date, $rows, $user, $enrollments, &$newlyAbsent): void {
            foreach ($rows as $row) {
                $attendance = StudentAttendance::where('enrollment_id', $row['enrollment_id'])->whereDate('date', $date)->first()
                    ?? new StudentAttendance(['enrollment_id' => $row['enrollment_id'], 'date' => $date]);
                $attendance->fill([
                    'student_id' => $enrollments[$row['enrollment_id']],
                    'section_id' => $section->id,
                    'status' => $row['status'],
                    'remarks' => $row['remarks'] ?? null,
                ]);

                if ($attendance->isDirty('status') && $attendance->status === 'absent') {
                    $newlyAbsent[] = $attendance->student_id;
                }

                if ($attendance->isDirty()) {
                    $attendance->marked_by = $user->id;
                    $attendance->save();
                }
            }
        });

        // Families of students newly marked absent get the "absent alert" message templates.
        if ($newlyAbsent !== []) {
            $classSection = $section->loadMissing('schoolClass:id,name')->schoolClass?->name.' '.$section->name;

            foreach (Student::whereIn('id', $newlyAbsent)->get(['id', 'first_name', 'middle_name', 'last_name']) as $student) {
                $this->notificationService->notifyEvent('absent_alert', $this->notificationService->familyUserIds([$student->id])->all(), [
                    'student_name' => $student->fullName(), 'class_section' => $classSection, 'date' => Carbon::parse($date)->format('d M Y'),
                ], $student);
            }
        }

        return collect($rows)->countBy('status')->all();
    }

    /**
     * Monthly register summary for a section: counts per status and attendance percentage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monthly(Section $section, string $month): array
    {
        $from = Carbon::parse($month.'-01');
        $counts = StudentAttendance::query()
            ->where('section_id', $section->id)
            ->whereBetween('date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()])
            ->select(['student_id', 'status', DB::raw('count(*) as days')])
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id');

        return Enrollment::where('section_id', $section->id)->with('student:id,first_name,last_name,admission_no')->get(['id', 'student_id', 'roll_no'])
            ->map(function (Enrollment $enrollment) use ($counts): array {
                $byStatus = collect($counts->get($enrollment->student_id, []))->pluck('days', 'status')->map(fn ($days): int => (int) $days);

                return [
                    'student_id' => $enrollment->student_id,
                    'name' => $enrollment->student->fullName(),
                    'admission_no' => $enrollment->student->admission_no,
                    'roll_no' => $enrollment->roll_no,
                    ...collect(StudentAttendance::STATUSES)->mapWithKeys(fn (string $status): array => [$status => $byStatus->get($status, 0)])->all(),
                    'percentage' => $this->percentage($byStatus->all()),
                ];
            })
            ->sortBy('roll_no')
            ->values()
            ->all();
    }

    /**
     * Attendance for one student (parent portal, student portal, mobile app).
     *
     * @return array{from: string, to: string, percentage: float|null, counts: array<string, int>, days: array<int, array{date: string, status: string}>}
     */
    public function forStudent(Student $student, string $from, string $to): array
    {
        $days = StudentAttendance::where('student_id', $student->id)->whereBetween('date', [$from, $to])->orderBy('date')->get(['date', 'status']);
        $counts = $days->countBy('status')->all();

        return [
            'from' => $from,
            'to' => $to,
            'percentage' => $this->percentage($counts),
            'counts' => collect(StudentAttendance::STATUSES)->mapWithKeys(fn (string $status): array => [$status => $counts[$status] ?? 0])->all(),
            'days' => $days->map(fn (StudentAttendance $day): array => ['date' => $day->date->toDateString(), 'status' => $day->status])->all(),
        ];
    }

    /* ---------- Staff ---------- */

    /**
     * @return array{date: string, holiday: string|null, rows: array<int, array<string, mixed>>}
     */
    public function staffSheet(string $date): array
    {
        $saved = StaffAttendance::whereDate('date', $date)->get()->keyBy('employee_id');

        return [
            'date' => $date,
            'holiday' => $this->calendarService->holidayOn($date)?->title,
            'rows' => Employee::whereIn('status', Employee::CURRENT_STATUSES)->with('designation:id,name')->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code', 'designation_id'])
                ->map(fn (Employee $employee): array => [
                    'employee_id' => $employee->id,
                    'name' => $employee->fullName(),
                    'employee_code' => $employee->employee_code,
                    'designation' => $employee->designation?->name,
                    'status' => $saved->get($employee->id)?->status,
                    'check_in_at' => $saved->get($employee->id)?->check_in_at ? substr($saved->get($employee->id)->check_in_at, 0, 5) : null,
                    'check_out_at' => $saved->get($employee->id)?->check_out_at ? substr($saved->get($employee->id)->check_out_at, 0, 5) : null,
                    'remarks' => $saved->get($employee->id)?->remarks,
                ])->all(),
        ];
    }

    /**
     * @param  array<int, array{employee_id: int, status: string, check_in_at?: string|null, check_out_at?: string|null, remarks?: string|null}>  $rows
     * @return array<string, int>
     */
    public function markStaff(string $date, array $rows, User $user): array
    {
        $this->ensureSchoolDay($date);

        DB::transaction(function () use ($date, $rows, $user): void {
            foreach ($rows as $row) {
                $attendance = StaffAttendance::where('employee_id', $row['employee_id'])->whereDate('date', $date)->first()
                    ?? new StaffAttendance(['employee_id' => $row['employee_id'], 'date' => $date]);
                $attendance->fill([
                    'status' => $row['status'],
                    'check_in_at' => $row['check_in_at'] ?? null,
                    'check_out_at' => $row['check_out_at'] ?? null,
                    'remarks' => $row['remarks'] ?? null,
                ]);

                if ($attendance->isDirty()) {
                    $attendance->marked_by = $user->id;
                    $attendance->save();
                }
            }
        });

        return collect($rows)->countBy('status')->all();
    }

    /**
     * Gate scan of an ID card QR: a student is marked present for today; a staff member is
     * marked in, and a second scan the same day records the check-out time.
     *
     * @return array{name: string|null, holder_type: string, status: string, time: string, action: string}
     *
     * @throws ValidationException for unknown / inactive cards, holidays, or missing permission
     */
    public function scan(string $code, User $user): array
    {
        $token = preg_match('~/verify/([A-Za-z0-9]+)~', $code, $match) ? $match[1] : trim($code);
        $card = IdCard::where('qr_token', $token)->first();

        if ($card === null || $card->status !== 'active') {
            throw ValidationException::withMessages(['code' => [$card === null ? 'This card is not recognised.' : "This card is {$card->status}."]]);
        }

        $today = today()->toDateString();
        $this->ensureSchoolDay($today);
        $time = now()->format('H:i:s');

        if ($card->holder_type === 'employee') {
            if (! $user->hasPermission('manage-staff-attendance')) {
                throw ValidationException::withMessages(['code' => ['You cannot mark staff attendance.']]);
            }

            $employee = Employee::findOrFail($card->holder_id);

            return DB::transaction(function () use ($employee, $today, $time, $user): array {
                $attendance = StaffAttendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();

                if ($attendance === null) {
                    StaffAttendance::create(['employee_id' => $employee->id, 'date' => $today, 'status' => 'present', 'check_in_at' => $time, 'source' => 'qr', 'marked_by' => $user->id]);

                    return ['name' => $employee->fullName(), 'holder_type' => 'employee', 'status' => 'present', 'time' => substr($time, 0, 5), 'action' => 'checked_in'];
                }

                $attendance->update(['check_out_at' => $time, 'source' => 'qr']);

                return ['name' => $employee->fullName(), 'holder_type' => 'employee', 'status' => $attendance->status, 'time' => substr($time, 0, 5), 'action' => 'checked_out'];
            });
        }

        if (! $user->hasPermission('record-attendance')) {
            throw ValidationException::withMessages(['code' => ['You cannot mark student attendance.']]);
        }

        $student = Student::findOrFail($card->holder_id);
        $enrollment = $student->currentEnrollment;

        if ($enrollment === null || $enrollment->section_id === null) {
            throw ValidationException::withMessages(['code' => ["{$student->fullName()} has no class section this session."]]);
        }

        return DB::transaction(function () use ($student, $enrollment, $today, $time, $user): array {
            $attendance = StudentAttendance::where('enrollment_id', $enrollment->id)->whereDate('date', $today)->first();

            if ($attendance === null) {
                StudentAttendance::create(['enrollment_id' => $enrollment->id, 'student_id' => $student->id, 'section_id' => $enrollment->section_id, 'date' => $today, 'status' => 'present', 'check_in_at' => $time, 'marked_by' => $user->id]);
            }

            return ['name' => $student->fullName(), 'holder_type' => 'student', 'status' => $attendance?->status ?? 'present', 'time' => substr($time, 0, 5), 'action' => $attendance ? 'already_marked' : 'checked_in'];
        });
    }

    /* ---------- Leave requests ---------- */

    /**
     * @param  array{status?: string|null, student_ids?: array<int, int>}  $filters
     */
    public function leaveQuery(array $filters = []): Builder
    {
        return StudentLeaveRequest::query()
            ->with(['student:id,first_name,last_name,admission_no', 'requester:id,name', 'reviewer:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(array_key_exists('student_ids', $filters), fn (Builder $query) => $query->whereIn('student_id', $filters['student_ids']));
    }

    /**
     * @return array<string, mixed>
     */
    public function leaveDatatable(array $filters): array
    {
        return DataTables::eloquent($this->leaveQuery($filters))
            ->escapeColumns([])
            ->addColumn('student_name', fn (StudentLeaveRequest $leave): ?string => $leave->student?->fullName())
            ->addColumn('admission_no', fn (StudentLeaveRequest $leave): ?string => $leave->student?->admission_no)
            ->addColumn('requested_by_name', fn (StudentLeaveRequest $leave): ?string => $leave->requester?->name)
            ->addColumn('reviewed_by_name', fn (StudentLeaveRequest $leave): ?string => $leave->reviewer?->name)
            ->editColumn('from_date', fn (StudentLeaveRequest $leave): string => $leave->from_date->toDateString())
            ->editColumn('to_date', fn (StudentLeaveRequest $leave): string => $leave->to_date->toDateString())
            ->removeColumn('student', 'requester', 'reviewer')
            ->toArray();
    }

    /**
     * @param  array{student_id: int, from_date: string, to_date: string, reason: string}  $data
     */
    public function requestLeave(array $data, User $user): StudentLeaveRequest
    {
        return DB::transaction(fn (): StudentLeaveRequest => StudentLeaveRequest::create([...$data, 'requested_by' => $user->id, 'status' => 'pending'])
            ->load(['student:id,first_name,last_name,admission_no', 'requester:id,name']));
    }

    /**
     * @throws ValidationException when it was already decided
     */
    public function decideLeave(StudentLeaveRequest $leave, bool $approve, ?string $remarks, User $user): StudentLeaveRequest
    {
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ["This request was already {$leave->status}."]]);
        }

        return DB::transaction(function () use ($leave, $approve, $remarks, $user): StudentLeaveRequest {
            $leave->update(['status' => $approve ? 'approved' : 'rejected', 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'review_remarks' => $remarks]);

            return $leave->load(['student:id,first_name,last_name,admission_no', 'requester:id,name', 'reviewer:id,name']);
        });
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function percentage(array $counts): ?float
    {
        $total = array_sum($counts);

        if ($total === 0) {
            return null;
        }

        $attended = collect($counts)->only(StudentAttendance::ATTENDED)->sum() - ($counts['half_day'] ?? 0) / 2;

        return round($attended * 100 / $total, 1);
    }

    /**
     * @throws ValidationException for future dates and holidays
     */
    private function ensureSchoolDay(string $date): void
    {
        if (Carbon::parse($date)->isAfter(today())) {
            throw ValidationException::withMessages(['date' => ['Attendance cannot be marked for a future date.']]);
        }

        if (($holiday = $this->calendarService->holidayOn($date)) !== null) {
            throw ValidationException::withMessages(['date' => ["{$date} is a holiday ({$holiday->title})."]]);
        }
    }
}
