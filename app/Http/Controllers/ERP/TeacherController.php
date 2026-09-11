<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\RecordAttendanceRequest;
use App\Http\Requests\ERP\SubmitGradesRequest;
use App\Services\TeacherService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    use ApiResponse;

    protected TeacherService $teacherService;

    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }

    /**
     * Teacher Dashboard Overview.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->teacherService->getTeacherDashboard($request->user());

        return $this->successResponse($data, 'Teacher dashboard retrieved successfully.');
    }

    /**
     * Get Teacher Assigned Classes.
     */
    public function classes(Request $request): JsonResponse
    {
        $classes = $this->teacherService->getAssignedClasses($request->user());

        return $this->successResponse($classes, 'Assigned classes retrieved successfully.');
    }

    /**
     * Record Class Attendance.
     */
    public function recordAttendance(RecordAttendanceRequest $request): JsonResponse
    {
        $result = $this->teacherService->recordAttendance($request->user(), $request->validated());

        return $this->successResponse($result, 'Class attendance recorded successfully.');
    }

    /**
     * Submit Student Grades.
     */
    public function submitGrades(SubmitGradesRequest $request): JsonResponse
    {
        $result = $this->teacherService->submitGrades($request->user(), $request->validated());

        return $this->successResponse($result, 'Student grades recorded successfully.');
    }
}
