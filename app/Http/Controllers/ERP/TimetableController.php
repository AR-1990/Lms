<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveTimetableSlotRequest;
use App\Http\Resources\TimetableSlotResource;
use App\Models\Employee;
use App\Models\Section;
use App\Models\TimetableSlot;
use App\Services\TimetableService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private TimetableService $timetableService) {}

    public function section(Section $section): JsonResponse
    {
        return $this->successResponse(TimetableSlotResource::collection($this->timetableService->forSection($section))->resolve(), 'Timetable retrieved.');
    }

    /**
     * The signed-in teacher's own week.
     */
    public function mine(Request $request): JsonResponse
    {
        $teacher = Employee::where('user_id', $request->user()->id)->first();

        return $this->successResponse($teacher ? TimetableSlotResource::collection($this->timetableService->forTeacher($teacher))->resolve() : [], 'Timetable retrieved.');
    }

    public function store(SaveTimetableSlotRequest $request): JsonResponse
    {
        return $this->successResponse(new TimetableSlotResource($this->timetableService->save($request->validated())), 'Timetable updated.', 201);
    }

    public function destroy(TimetableSlot $timetableSlot): JsonResponse
    {
        return $this->deleteWithApproval($timetableSlot, 'Period cleared.');
    }
}
