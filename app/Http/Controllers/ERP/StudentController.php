<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\StudentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use ApiResponse;

    protected StudentService $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * Student Dashboard Overview.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->studentService->getStudentDashboard($request->user());

        return $this->successResponse($data, 'Student dashboard retrieved successfully.');
    }

    /**
     * Get Enrolled Courses.
     */
    public function courses(Request $request): JsonResponse
    {
        $courses = $this->studentService->getEnrolledCourses($request->user());

        return $this->successResponse($courses, 'Enrolled courses retrieved successfully.');
    }

    /**
     * Get Attendance Records.
     */
    public function attendance(Request $request): JsonResponse
    {
        $attendance = $this->studentService->getAttendance($request->user());

        return $this->successResponse($attendance, 'Student attendance summary retrieved successfully.');
    }

    /**
     * Get Grades & Academic Transcript.
     */
    public function grades(Request $request): JsonResponse
    {
        $grades = $this->studentService->getGrades($request->user());

        return $this->successResponse($grades, 'Student grades retrieved successfully.');
    }
}
