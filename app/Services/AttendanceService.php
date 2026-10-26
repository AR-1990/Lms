<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AttendanceKiosk;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\IdCard;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentLeaveRequest;
use App\Models\SystemSetting;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Models\WorkShift;
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
        private ActivityLogger $activityLogger,
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

                if ($attendance->exists && $attendance->isDirty('status')) {
                    $attendance->source = 'manual';
                }

                if ($attendance->isDirty('status') && $attendance->status === 'absent') {
                    $newlyAbsent[] = $attendance->student_id;
                }

                if ($attendance->isDirty()) {
                    $attendance->marked_by = $user->id;
                    $attendance->save();
                }
            }
        });

        $this->alertAbsent($newlyAbsent, $date);

        return collect($rows)->countBy('status')->all();
    }

    /**
     * @return array{auto_absent_at: string|null}
     */
    public function settings(): array
    {
        $time = SystemSetting::query()->value('auto_absent_at');

        return ['auto_absent_at' => $time ? substr($time, 0, 5) : null];
    }

    /**
     * @param  array{auto_absent_at: string|null}  $data
     * @return array{auto_absent_at: string|null}
     */
    public function saveSettings(array $data, User $user): array
    {
        SystemSetting::query()->firstOrFail()->update($data);
        $this->activityLogger->record('attendance', 'settings', $data['auto_absent_at'] ? "{$user->name} set students with no mark to be made absent at {$data['auto_absent_at']}" : "{$user->name} switched off automatic absent marking");

        return $this->settings();
    }

    /**
     * For schools that take attendance at the gate: after the chosen time, every student of the current
     * session with no mark today is made absent (or "leave" with approved leave) and the family gets the
     * absent alert. Skips Sundays and holidays. A student who arrives later becomes "late" at the gate,
     * and teachers can still change any mark in the register. Returns how many were marked.
     */
    public function autoAbsent(?Carbon $now = null): int
    {
        $now ??= now();
        $time = SystemSetting::query()->value('auto_absent_at');
        $today = $now->toDateString();

        if ($time === null || $now->format('H:i:s') < $time || $now->isSunday() || $this->calendarService->holidayOn($today) !== null) {
            return 0;
        }

        $enrollments = Enrollment::where('status', 'active')
            ->whereIn('academic_session_id', AcademicSession::where('is_current', true)->select('id'))
            ->whereNotNull('section_id')
            ->whereNotExists(fn ($marked) => $marked->from('student_attendances')->whereColumn('student_attendances.enrollment_id', 'enrollments.id')->whereDate('student_attendances.date', $today))
            ->get(['id', 'student_id', 'section_id']);
        $onLeave = StudentLeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', $today)->whereDate('to_date', '>=', $today)->whereIn('student_id', $enrollments->pluck('student_id'))->pluck('student_id')->all();
        $absent = [];

        DB::transaction(function () use ($enrollments, $onLeave, $today, &$absent): void {
            foreach ($enrollments as $enrollment) {
                $status = in_array($enrollment->student_id, $onLeave, true) ? 'leave' : 'absent';
                StudentAttendance::create(['enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'section_id' => $enrollment->section_id, 'date' => $today, 'status' => $status, 'source' => 'auto']);
                if ($status === 'absent') {
                    $absent[] = $enrollment->student_id;
                }
            }

            if ($enrollments->isNotEmpty()) {
                $this->activityLogger->record('attendance', 'auto_absent', count($absent).' student(s) with no mark made absent, '.($enrollments->count() - count($absent)).' on leave');
            }
        });

        $this->alertAbsent($absent, $today);

        return $enrollments->count();
    }

    /**
     * Families of students newly marked absent get the "absent alert" message templates.
     *
     * @param  array<int, int>  $studentIds
     */
    private function alertAbsent(array $studentIds, string $date): void
    {
        if ($studentIds === []) {
            return;
        }

        $students = Student::whereIn('id', $studentIds)->with('currentEnrollment.section.schoolClass:id,name')->get(['id', 'first_name', 'middle_name', 'last_name']);

        foreach ($students as $student) {
            $section = $student->currentEnrollment?->section;
            $this->notificationService->notifyEvent('absent_alert', $this->notificationService->familyUserIds([$student->id])->all(), [
                'student_name' => $student->fullName(), 'class_section' => trim(($section?->schoolClass?->name ?? '').' '.($section?->name ?? '')), 'date' => Carbon::parse($date)->format('d M Y'),
            ], $student);
        }
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
                    ...$this->shiftStatus((int) $row['employee_id'], $row['status'], $row['check_in_at'] ?? null),
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
     * Gate scan of an ID card QR (by a signed-in user, or by a kiosk without sign-in): a student is
     * marked present for today; a staff member is marked in, and a second scan records the check-out.
     *
     * @return array{name: string|null, holder_type: string, status: string, time: string, action: string}
     *
     * @throws ValidationException for unknown / inactive cards, holidays, another campus's card, or missing permission
     */
    public function scan(string $code, ?User $user, ?AttendanceKiosk $kiosk = null): array
    {
        $markedBy = $user?->id ?? $kiosk?->created_by;
        $source = $kiosk !== null ? 'kiosk' : 'qr';
        $token = preg_match('~/verify/([A-Za-z0-9]+)~', $code, $match) ? $match[1] : trim($code);
        $card = IdCard::where('qr_token', $token)->first();

        if ($card === null || $card->status !== 'active') {
            throw ValidationException::withMessages(['code' => [$card === null ? 'This card is not recognised.' : "This card is {$card->status}."]]);
        }

        $today = today()->toDateString();
        $this->ensureSchoolDay($today);
        $time = now()->format('H:i:s');

        if ($kiosk?->campus_id !== null && ($holderCampus = $card->holder_type === 'employee' ? Employee::whereKey($card->holder_id)->value('campus_id') : Student::whereKey($card->holder_id)->value('campus_id')) !== null && $holderCampus !== $kiosk->campus_id) {
            throw ValidationException::withMessages(['code' => ['This card belongs to another campus.']]);
        }

        if ($card->holder_type === 'employee') {
            if ($kiosk === null && ! $user?->hasPermission('manage-staff-attendance')) {
                throw ValidationException::withMessages(['code' => ['You cannot mark staff attendance.']]);
            }

            $employee = Employee::findOrFail($card->holder_id);

            return DB::transaction(function () use ($employee, $today, $time, $markedBy, $source): array {
                $attendance = StaffAttendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();

                if ($attendance === null) {
                    $status = $this->shiftStatus($employee->id, 'present', $time);
                    StaffAttendance::create([...$status, 'employee_id' => $employee->id, 'date' => $today, 'check_in_at' => $time, 'source' => $source, 'marked_by' => $markedBy]);

                    return ['name' => $employee->fullName(), 'holder_type' => 'employee', 'status' => $status['status'], 'time' => substr($time, 0, 5), 'action' => 'checked_in'];
                }

                $attendance->update(['check_out_at' => $time, 'source' => $source]);

                return ['name' => $employee->fullName(), 'holder_type' => 'employee', 'status' => $attendance->status, 'time' => substr($time, 0, 5), 'action' => 'checked_out'];
            });
        }

        if ($kiosk === null && ! $user?->hasPermission('record-attendance')) {
            throw ValidationException::withMessages(['code' => ['You cannot mark student attendance.']]);
        }

        $student = Student::findOrFail($card->holder_id);
        $enrollment = $student->currentEnrollment;

        if ($enrollment === null || $enrollment->section_id === null) {
            throw ValidationException::withMessages(['code' => ["{$student->fullName()} has no class section this session."]]);
        }

        $result = DB::transaction(function () use ($student, $enrollment, $today, $time, $markedBy, $source): array {
            $attendance = StudentAttendance::where('enrollment_id', $enrollment->id)->whereDate('date', $today)->first();

            // Made absent automatically, then arrived: late from the gate time.
            if ($attendance !== null && $attendance->source === 'auto' && $attendance->status === 'absent') {
                $attendance->update(['status' => 'late', 'check_in_at' => $time, 'source' => $source, 'marked_by' => $markedBy]);

                return ['name' => $student->fullName(), 'holder_type' => 'student', 'status' => 'late', 'time' => substr($time, 0, 5), 'action' => 'checked_in'];
            }

            if ($attendance === null) {
                StudentAttendance::create(['enrollment_id' => $enrollment->id, 'student_id' => $student->id, 'section_id' => $enrollment->section_id, 'date' => $today, 'status' => 'present', 'check_in_at' => $time, 'source' => $source, 'marked_by' => $markedBy]);
            }

            return ['name' => $student->fullName(), 'holder_type' => 'student', 'status' => $attendance?->status ?? 'present', 'time' => substr($time, 0, 5), 'action' => $attendance ? 'already_marked' : 'checked_in'];
        });

        if ($result['action'] === 'checked_in') {
            $this->notificationService->notifyEvent('student_arrived', $this->notificationService->familyUserIds([$student->id])->all(), [
                'student_name' => $student->fullName(), 'time' => Carbon::parse($time)->format('h:i A'), 'date' => Carbon::parse($today)->format('d M Y'),
            ], $student);
        }

        return $result;
    }

    /**
     * What the gate screen / kiosk says after a scan.
     *
     * @param  array{name: string|null, status: string, time: string, action: string}  $result
     */
    public function scanMessage(array $result): string
    {
        return match (true) {
            $result['action'] === 'checked_out' => "{$result['name']} checked out at {$result['time']}.",
            $result['action'] === 'already_marked' => "{$result['name']} is already marked {$result['status']} today.",
            $result['status'] === 'late' => "{$result['name']} checked in late at {$result['time']}.",
            default => "{$result['name']} marked present at {$result['time']}.",
        };
    }

    /**
     * Today's live attendance: students per section (present / late / absent / leave / not marked yet) and
     * staff (in / out / late / not in), with the latest arrivals from the gate, scanners and registers.
     * Teachers see only the sections they teach; staff counts need manage-staff-attendance.
     *
     * @return array<string, mixed>
     */
    public function live(User $user): array
    {
        $today = today()->toDateString();
        $recent = collect();
        $result = ['date' => $today, 'time' => now()->format('H:i:s'), 'holiday' => $this->calendarService->holidayOn($today)?->title, 'students' => null, 'staff' => null];

        if ($user->hasPermission('record-attendance')) {
            $employeeId = Employee::where('user_id', $user->id)->value('id') ?? 0;
            $sections = Section::query()
                ->join('classes', 'classes.id', '=', 'sections.class_id')
                ->select(['sections.id', 'sections.name', 'classes.name as class_name'])
                ->whereIn('sections.academic_session_id', AcademicSession::where('is_current', true)->select('id'))
                ->when(! $user->hasPermission('manage-students'), fn (Builder $query) => $query->where(fn (Builder $own) => $own->where('sections.class_teacher_id', $employeeId)
                    ->orWhereIn('sections.id', TeacherAssignment::where('employee_id', $employeeId)->select('section_id'))))
                ->withCount(['enrollments as total' => fn (Builder $query) => $query->where('status', 'active')])
                ->orderBy('classes.level_order')->orderBy('sections.name')
                ->get();
            $counts = StudentAttendance::whereDate('date', $today)->whereIn('section_id', $sections->pluck('id'))
                ->selectRaw('section_id, status, count(*) as total')->groupBy('section_id', 'status')->get()->groupBy('section_id');

            $rows = $sections->map(function (Section $section) use ($counts): array {
                $status = ($counts->get($section->id) ?? collect())->pluck('total', 'status');
                $marked = (int) $status->sum();

                return [
                    'section_id' => $section->id, 'name' => trim($section->class_name.' '.$section->name), 'total' => (int) $section->total,
                    'present' => (int) $status->get('present', 0) + (int) $status->get('late', 0) + (int) $status->get('half_day', 0),
                    'late' => (int) $status->get('late', 0), 'absent' => (int) $status->get('absent', 0), 'leave' => (int) $status->get('leave', 0),
                    'unmarked' => max(0, $section->total - $marked),
                ];
            });

            $rows = $rows->where('total', '>', 0)->values();
            $result['students'] = ['totals' => collect(['total', 'present', 'late', 'absent', 'leave', 'unmarked'])->mapWithKeys(fn (string $key): array => [$key => $rows->sum($key)])->all(), 'sections' => $rows->values()->all()];
            $recent = $recent->merge(StudentAttendance::whereDate('date', $today)->whereIn('section_id', $sections->pluck('id'))->whereNotNull('check_in_at')
                ->with('student:id,first_name,middle_name,last_name')->latest('updated_at')->limit(25)->get()
                ->map(fn (StudentAttendance $row): array => ['type' => 'student', 'name' => $row->student?->fullName(), 'detail' => $rows->firstWhere('section_id', $row->section_id)['name'] ?? null, 'status' => $row->status, 'action' => 'checked_in', 'time' => substr((string) $row->check_in_at, 0, 5), 'source' => $row->source, 'at' => $row->updated_at]));
        }

        if ($user->hasPermission('manage-staff-attendance')) {
            $staffRows = StaffAttendance::whereDate('date', $today)->get(['id', 'employee_id', 'status', 'check_in_at', 'check_out_at', 'source', 'updated_at']);
            $total = Employee::whereIn('status', Employee::CURRENT_STATUSES)->count();
            $result['staff'] = [
                'total' => $total,
                'in' => $staffRows->whereNotNull('check_in_at')->whereNull('check_out_at')->count(),
                'out' => $staffRows->whereNotNull('check_out_at')->count(),
                'late' => $staffRows->where('status', 'late')->count(),
                'absent' => $staffRows->where('status', 'absent')->count(),
                'leave' => $staffRows->where('status', 'leave')->count(),
                'not_in' => max(0, $total - $staffRows->count()),
            ];
            $employees = Employee::whereIn('id', $staffRows->sortByDesc('updated_at')->take(25)->pluck('employee_id'))->with('designation:id,name')->get(['id', 'first_name', 'middle_name', 'last_name', 'designation_id'])->keyBy('id');
            $recent = $recent->merge($staffRows->whereNotNull('check_in_at')->sortByDesc('updated_at')->take(25)->map(fn (StaffAttendance $row): array => [
                'type' => 'staff', 'name' => $employees->get($row->employee_id)?->fullName(), 'detail' => $employees->get($row->employee_id)?->designation?->name, 'status' => $row->status,
                'action' => $row->check_out_at ? 'checked_out' : 'checked_in', 'time' => substr((string) ($row->check_out_at ?? $row->check_in_at), 0, 5), 'source' => $row->source, 'at' => $row->updated_at,
            ]));
        }

        $result['recent'] = $recent->sortByDesc('at')->take(30)->map(fn (array $event): array => [...$event, 'at' => $event['at']?->toIso8601String()])->values()->all();

        return $result;
    }

    /**
     * Biometric device export: each employee's first punch of a day is the check-in and the last the
     * check-out; lateness follows their shift. Days already marked by hand keep their status.
     *
     * @param  array<int, array{employee_id: int, date: string, times: array<int, string>}>  $days
     * @return array{saved: int, skipped: int}
     */
    public function importPunches(array $days, User $user): array
    {
        $counts = ['saved' => 0, 'skipped' => 0];

        DB::transaction(function () use ($days, $user, &$counts): void {
            foreach ($days as $day) {
                sort($day['times']);
                $in = $day['times'][0];
                $out = count($day['times']) > 1 ? end($day['times']) : null;
                $attendance = StaffAttendance::where('employee_id', $day['employee_id'])->whereDate('date', $day['date'])->first();

                if ($attendance !== null && $attendance->source === 'manual') {
                    $counts['skipped']++;

                    continue;
                }

                $attendance ??= new StaffAttendance(['employee_id' => $day['employee_id'], 'date' => $day['date']]);
                $attendance->fill([...$this->shiftStatus($day['employee_id'], 'present', $in), 'check_in_at' => $in, 'check_out_at' => $out, 'source' => 'biometric', 'marked_by' => $user->id])->save();
                $counts['saved']++;
            }
        });

        return $counts;
    }

    /**
     * Present with a check-in after the shift's grace minutes becomes late (with the minutes); other statuses stay.
     *
     * @return array{status: string, late_minutes: int}
     */
    private function shiftStatus(int $employeeId, string $status, ?string $checkIn): array
    {
        if (! in_array($status, ['present', 'late'], true) || blank($checkIn)) {
            return ['status' => $status, 'late_minutes' => 0];
        }

        $shift = WorkShift::for(Employee::findOrFail($employeeId));
        $late = $shift?->lateMinutes($checkIn) ?? 0;

        return ['status' => $late > 0 ? 'late' : 'present', 'late_minutes' => $late];
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

            $student = $leave->student()->first(['id', 'first_name', 'middle_name', 'last_name']);
            $this->notificationService->notifyEvent('leave_decided', $this->notificationService->familyUserIds([$leave->student_id])->all(), [
                'student_name' => $student?->fullName(), 'decision' => $approve ? 'approved' : 'rejected', 'from' => $leave->from_date?->format('d M'), 'to' => $leave->to_date?->format('d M Y'),
            ], $leave, route('family.child', $leave->student_id));

            return $leave->load(['student:id,first_name,last_name,admission_no', 'requester:id,name', 'reviewer:id,name']);
        });
    }

    /**
     * @param  array<string, int>  $counts
     */
    public function percentage(array $counts): ?float
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
