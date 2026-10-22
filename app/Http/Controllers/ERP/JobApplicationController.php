<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ChangeJobApplicationStatusRequest;
use App\Http\Requests\Hr\HireCandidateRequest;
use App\Http\Requests\Hr\SaveJobApplicationRequest;
use App\Http\Requests\Hr\SaveJobInterviewRequest;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\JobApplicationResource;
use App\Http\Resources\JobInterviewResource;
use App\Models\JobApplication;
use App\Models\JobInterview;
use App\Services\RecruitmentService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private RecruitmentService $recruitmentService) {}

    public function index(Request $request): JsonResponse
    {
        $applications = $this->recruitmentService->applicationsQuery($this->filters($request))->latest()->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($applications, JobApplicationResource::class, 'Applications retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->recruitmentService->applicationsDatatable($this->filters($request)), 'Applications retrieved.');
    }

    public function store(SaveJobApplicationRequest $request): JsonResponse
    {
        return $this->successResponse(new JobApplicationResource($this->recruitmentService->createApplication($request->validated())), 'Application received.', 201);
    }

    public function show(JobApplication $jobApplication): JsonResponse
    {
        return $this->successResponse(new JobApplicationResource($this->recruitmentService->findApplication($jobApplication)), 'Application retrieved.');
    }

    public function update(SaveJobApplicationRequest $request, JobApplication $jobApplication): JsonResponse
    {
        return $this->successResponse(new JobApplicationResource($this->recruitmentService->updateApplication($jobApplication, $request->validated())), 'Application updated.');
    }

    public function destroy(JobApplication $jobApplication): JsonResponse
    {
        return $this->deleteWithApproval($jobApplication, 'Application deleted.');
    }

    public function changeStatus(ChangeJobApplicationStatusRequest $request, JobApplication $jobApplication): JsonResponse
    {
        $updated = $this->recruitmentService->changeStatus($jobApplication, $request->validated('status'), $request->validated('remarks'), $request->validated('rejection_reason_id'));

        return $this->successResponse(new JobApplicationResource($updated), 'Moved to '.str_replace('_', ' ', $updated->status).'.');
    }

    public function storeInterview(SaveJobInterviewRequest $request, JobApplication $jobApplication): JsonResponse
    {
        return $this->successResponse(new JobInterviewResource($this->recruitmentService->scheduleInterview($jobApplication, $request->validated())), 'Interview scheduled.', 201);
    }

    public function updateInterview(SaveJobInterviewRequest $request, JobInterview $jobInterview): JsonResponse
    {
        return $this->successResponse(new JobInterviewResource($this->recruitmentService->updateInterview($jobInterview, $request->validated())), 'Interview updated.');
    }

    public function hire(HireCandidateRequest $request, JobApplication $jobApplication): JsonResponse
    {
        $employee = $this->recruitmentService->hire($jobApplication, $request->validated());

        return $this->successResponse(new EmployeeResource($employee), "{$employee->fullName()} hired as {$employee->employee_code}.", 201);
    }

    /**
     * @return array{job_opening_id: int|null, status: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'job_opening_id' => $request->integer('job_opening_id') ?: null,
            'status' => is_string($request->input('status')) ? ($request->input('status') ?: null) : null,
        ];
    }
}
