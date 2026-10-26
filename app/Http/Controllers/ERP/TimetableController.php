<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveTimetableSlotRequest;
use App\Http\Resources\TimetableSlotResource;
use App\Models\Employee;
use App\Models\Section;
use App\Models\Substitution;
use App\Models\TimetableSlot;
use App\Services\SubstitutionService;
use App\Services\TimetableService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimetableController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private TimetableService $timetableService, private SubstitutionService $substitutionService) {}

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

    /* ---------- Cover for absent teachers ---------- */

    public function substitutions(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? today()->toDateString();

        return $this->successResponse($this->substitutionService->day($date), 'Periods to cover.');
    }

    public function assignSubstitution(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'timetable_slot_id' => ['required', 'integer'],
            'substitute_teacher_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $substitution = $this->substitutionService->assign($data, $request->user());

        return $this->successResponse($substitution, $substitution->substitute?->fullName().' will cover this period and has been told.', 201);
    }

    public function removeSubstitution(Substitution $substitution): JsonResponse
    {
        return $this->deleteWithApproval($substitution, 'Cover removed.');
    }
}
