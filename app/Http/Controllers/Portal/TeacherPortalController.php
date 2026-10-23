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
}
