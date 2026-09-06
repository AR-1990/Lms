<?php

namespace App\Http\Controllers\Portal;

use App\Services\StudentService;
use Illuminate\View\View;

class StudentPortalController extends PortalController
{
    public function __construct(private StudentService $studentService) {}

    public function courses(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.student.courses', 'courses', [
            'courses' => $this->studentService->getEnrolledCourses($user),
        ]);
    }

    public function attendance(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.student.attendance', 'attendance', $this->studentService->getAttendance($user));
    }

    public function grades(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.student.grades', 'grades', $this->studentService->getGrades($user));
    }
}
