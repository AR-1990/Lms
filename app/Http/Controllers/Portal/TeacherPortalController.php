<?php

namespace App\Http\Controllers\Portal;

use App\Services\TeacherService;
use Illuminate\View\View;

class TeacherPortalController extends PortalController
{
    public function __construct(private TeacherService $teacherService) {}

    public function classes(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.teacher.classes', 'teacher', 'classes', [
            'classes' => $this->teacherService->getAssignedClasses($user),
        ]);
    }

    public function attendance(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.teacher.attendance', 'teacher', 'attendance', $this->teacherService->getAttendanceRegister($user));
    }

    public function grades(): View
    {
        $user = $this->portalUser();

        return $this->page('dashboards.teacher.grades', 'teacher', 'grades', $this->teacherService->getGradebook($user));
    }
}
