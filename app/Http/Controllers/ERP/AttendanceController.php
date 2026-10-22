<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Http\Resources\StudentLeaveRequestResource;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentLeaveRequest;
use App\Services\AttendanceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Student and staff registers, summaries and student leave requests.
 */
class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(private AttendanceService $attendanceService) {}

    public function sheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $section = Section::findOrFail($validated['section_id']);
        $this->attendanceService->ensureCanMark($request->user(), $section);

        return $this->successResponse($this->attendanceService->sheet($section, $validated['date']), 'Register retrieved.');
    }

    public function store(MarkAttendanceRequest $request): JsonResponse
    {
        $counts = $this->attendanceService->mark(Section::findOrFail($request->validated('section_id')), $request->validated('date'), $request->validated('rows'), $request->user());

        return $this->successResponse($counts, 'Attendance saved.');
    }

    public function monthly(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')->whereNull('deleted_at')],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $section = Section::findOrFail($validated['section_id']);
        $this->attendanceService->ensureCanMark($request->user(), $section);

        return $this->successResponse($this->attendanceService->monthly($section, $validated['month']), 'Monthly attendance retrieved.');
    }

    /**
     * One student's attendance — staff, the student, or their parent.
     */
    public function student(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission(['record-attendance', 'manage-students']) || in_array($student->id, Student::ownIdsFor($user), true), 404);

        $validated = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return $this->successResponse(
            $this->attendanceService->forStudent($student, $validated['from'] ?? now()->startOfMonth()->toDateString(), $validated['to'] ?? now()->toDateString()),
            'Attendance retrieved.',
        );
    }

    public function staffSheet(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        return $this->successResponse($this->attendanceService->staffSheet($date), 'Staff register retrieved.');
    }

    public function storeStaff(MarkAttendanceRequest $request): JsonResponse
    {
        return $this->successResponse($this->attendanceService->markStaff($request->validated('date'), $request->validated('rows'), $request->user()), 'Staff attendance saved.');
    }

    /**
     * Gate scanner: the QR text (verify URL or token) from an ID card.
     */
    public function scan(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string', 'max:255']])['code'];
        $result = $this->attendanceService->scan($code, $request->user());
        $message = match ($result['action']) {
            'checked_out' => "{$result['name']} checked out at {$result['time']}.",
            'already_marked' => "{$result['name']} is already marked {$result['status']} today.",
            default => "{$result['name']} marked present at {$result['time']}.",
        };

        return $this->successResponse($result, $message);
    }

    /* ---------- Leave requests ---------- */

    /**
     * Staff see every request; parents and students see their own.
     */
    public function leaves(Request $request): JsonResponse
    {
        $leaves = $this->attendanceService->leaveQuery($this->leaveFilters($request))->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($leaves, StudentLeaveRequestResource::class, 'Leave requests retrieved.');
    }

    public function leavesDatatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->attendanceService->leaveDatatable($this->leaveFilters($request)), 'Leave requests retrieved.');
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'from_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        abort_unless($user->hasPermission('record-attendance') || in_array((int) $validated['student_id'], Student::ownIdsFor($user), true), 403, 'You can request leave only for your own child.');

        return $this->successResponse(new StudentLeaveRequestResource($this->attendanceService->requestLeave($validated, $user)), 'Leave request sent to the class teacher.', 201);
    }

    public function decideLeave(Request $request, StudentLeaveRequest $studentLeaveRequest): JsonResponse
    {
        $validated = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'remarks' => ['nullable', 'string', 'max:255']]);
        $leave = $this->attendanceService->decideLeave($studentLeaveRequest, $validated['decision'] === 'approved', $validated['remarks'] ?? null, $request->user());

        return $this->successResponse(new StudentLeaveRequestResource($leave), 'Leave request '.$leave->status.'.');
    }

    /**
     * @return array{status?: string|null, student_ids?: array<int, int>}
     */
    private function leaveFilters(Request $request): array
    {
        $filters = ['status' => $request->validate(['status' => ['nullable', Rule::in(StudentLeaveRequest::STATUSES)]])['status'] ?? null];

        if (! $request->user()->hasPermission('record-attendance')) {
            $filters['student_ids'] = Student::ownIdsFor($request->user());
        }

        return $filters;
    }
}
