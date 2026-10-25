<?php

namespace App\Http\Controllers\Portal;

use App\Models\Student;
use App\Services\FamilyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * One child's school page for parents and the student: the page shell only; every panel loads from /api/erp.
 */
class FamilyPortalController extends PortalController
{
    public function __construct(private FamilyService $familyService) {}

    public function home(): RedirectResponse|View
    {
        $children = $this->familyService->children($this->portalUser());
        abort_if($children->isEmpty(), 404, 'No student is linked to this account.');

        return redirect()->route('family.child', $children->first()['id']);
    }

    public function child(Student $student): View
    {
        $user = $this->portalUser();
        abort_unless($user->hasPermission('manage-students') || in_array($student->id, Student::ownIdsFor($user), true), 404);

        $portal = $user->hasRole('parent') ? 'parent' : ($user->hasRole('student') ? 'student' : 'admin');

        return $this->page('dashboards.family.child', $portal, 'family', [
            'student' => $student,
            'children' => $this->familyService->children($user),
            'is_parent' => $portal === 'parent',
        ]);
    }
}
