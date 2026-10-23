<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\TeacherService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in teacher's own dashboard and classes. Attendance is marked in the Attendance module
 * (/api/erp/attendance) and marks are entered in the Exams module (/api/erp/marks).
 */
class TeacherController extends Controller
{
    use ApiResponse;

    public function __construct(private TeacherService $teacherService) {}

    public function dashboard(Request $request): JsonResponse
    {
        return $this->successResponse($this->teacherService->getTeacherDashboard($request->user()), 'Teacher dashboard retrieved successfully.');
    }

    public function classes(Request $request): JsonResponse
    {
        return $this->successResponse($this->teacherService->getAssignedClasses($request->user()), 'Assigned classes retrieved successfully.');
    }
}
