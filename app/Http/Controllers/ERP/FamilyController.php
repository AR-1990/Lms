<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\FamilyService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parent / student view of a child (web portal and mobile app). Families see only their own children.
 */
class FamilyController extends Controller
{
    use ApiResponse;

    public function __construct(private FamilyService $familyService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse($this->familyService->children($request->user())->all(), 'Children retrieved.');
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage-students') || in_array($student->id, Student::ownIdsFor($request->user()), true), 404);

        return $this->successResponse($this->familyService->overview($student), 'Child overview retrieved.');
    }
}
