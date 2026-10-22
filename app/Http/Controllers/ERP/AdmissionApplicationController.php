<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\ChangeApplicationStatusRequest;
use App\Http\Requests\Admissions\EnrolApplicationRequest;
use App\Http\Requests\Admissions\SaveAdmissionInterviewRequest;
use App\Http\Requests\Admissions\SaveAdmissionTestRequest;
use App\Http\Requests\Admissions\SaveApplicationRequest;
use App\Http\Resources\AdmissionApplicationResource;
use App\Http\Resources\StudentResource;
use App\Models\AdmissionApplication;
use App\Models\AdmissionInterview;
use App\Models\AdmissionTest;
use App\Services\AdmissionService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionApplicationController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private AdmissionService $admissionService) {}

    public function index(Request $request): JsonResponse
    {
        $applications = $this->admissionService->applicationsQuery($this->filters($request))->latest('applied_on')->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($applications, AdmissionApplicationResource::class, 'Applications retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->admissionService->applicationsDatatable($this->filters($request)), 'Applications retrieved.');
    }

    public function store(SaveApplicationRequest $request): JsonResponse
    {
        $application = $this->admissionService->createApplication($request->validated());

        return $this->successResponse(new AdmissionApplicationResource($application), "Application {$application->application_no} submitted.", 201);
    }

    public function show(AdmissionApplication $admissionApplication): JsonResponse
    {
        return $this->successResponse(new AdmissionApplicationResource($this->admissionService->findApplication($admissionApplication)), 'Application retrieved.');
    }

    public function update(SaveApplicationRequest $request, AdmissionApplication $admissionApplication): JsonResponse
    {
        return $this->successResponse(new AdmissionApplicationResource($this->admissionService->updateApplication($admissionApplication, $request->validated())), 'Application updated.');
    }

    public function destroy(AdmissionApplication $admissionApplication): JsonResponse
    {
        return $this->deleteWithApproval($admissionApplication, 'Application deleted.');
    }

    public function changeStatus(ChangeApplicationStatusRequest $request, AdmissionApplication $admissionApplication): JsonResponse
    {
        $application = $this->admissionService->changeStatus($admissionApplication, $request->validated());

        return $this->successResponse(new AdmissionApplicationResource($application), 'Moved to '.str_replace('_', ' ', $application->status).'.');
    }

    public function storeTest(SaveAdmissionTestRequest $request, AdmissionApplication $admissionApplication): JsonResponse
    {
        $this->admissionService->addTest($admissionApplication, $request->validated());

        return $this->successResponse(new AdmissionApplicationResource($this->admissionService->findApplication($admissionApplication->refresh())), 'Entry test scheduled.', 201);
    }

    public function updateTest(SaveAdmissionTestRequest $request, AdmissionTest $admissionTest): JsonResponse
    {
        $this->admissionService->updateTest($admissionTest, $request->validated());

        return $this->successResponse(new AdmissionApplicationResource($this->admissionService->findApplication(AdmissionApplication::findOrFail($admissionTest->application_id))), 'Test updated.');
    }

    public function storeInterview(SaveAdmissionInterviewRequest $request, AdmissionApplication $admissionApplication): JsonResponse
    {
        $this->admissionService->addInterview($admissionApplication, $request->validated());

        return $this->successResponse(new AdmissionApplicationResource($this->admissionService->findApplication($admissionApplication->refresh())), 'Interview scheduled.', 201);
    }

    public function updateInterview(SaveAdmissionInterviewRequest $request, AdmissionInterview $admissionInterview): JsonResponse
    {
        $this->admissionService->updateInterview($admissionInterview, $request->validated());

        return $this->successResponse(new AdmissionApplicationResource($this->admissionService->findApplication(AdmissionApplication::findOrFail($admissionInterview->application_id))), 'Interview updated.');
    }

    public function enrol(EnrolApplicationRequest $request, AdmissionApplication $admissionApplication): JsonResponse
    {
        $student = $this->admissionService->enrol($admissionApplication, $request->validated());

        return $this->successResponse(new StudentResource($student), "{$student->fullName()} enrolled as {$student->admission_no}.", 201);
    }

    /**
     * @return array{academic_session_id: int|null, class_id: int|null, status: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'academic_session_id' => $request->integer('academic_session_id') ?: null,
            'class_id' => $request->integer('class_id') ?: null,
            'status' => is_string($request->input('status')) ? ($request->input('status') ?: null) : null,
        ];
    }
}
