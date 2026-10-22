<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\SaveAcademicSessionRequest;
use App\Http\Resources\AcademicSessionResource;
use App\Models\AcademicSession;
use App\Services\AcademicSessionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicSessionController extends Controller
{
    use ApiResponse;

    public function __construct(private AcademicSessionService $sessionService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse($this->sessionService->paginate($request->integer('per_page', 15)), AcademicSessionResource::class, 'Sessions retrieved.');
    }

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->sessionService->datatable(), 'Sessions retrieved.');
    }

    /**
     * Sessions, classes and subjects for dropdowns (web forms and the mobile app).
     */
    public function options(): JsonResponse
    {
        return $this->successResponse($this->sessionService->options(), 'Academic options retrieved.');
    }

    public function store(SaveAcademicSessionRequest $request): JsonResponse
    {
        return $this->successResponse(new AcademicSessionResource($this->sessionService->create($request->validated())), 'Session created.', 201);
    }

    public function show(AcademicSession $academicSession): JsonResponse
    {
        return $this->successResponse(new AcademicSessionResource($academicSession->loadCount(['sections', 'curriculum'])), 'Session retrieved.');
    }

    public function update(SaveAcademicSessionRequest $request, AcademicSession $academicSession): JsonResponse
    {
        return $this->successResponse(new AcademicSessionResource($this->sessionService->update($academicSession, $request->validated())), 'Session updated.');
    }

    public function destroy(AcademicSession $academicSession): JsonResponse
    {
        $this->sessionService->delete($academicSession);

        return $this->successResponse(null, 'Session deleted.');
    }
}
