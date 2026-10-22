<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\SaveApplicationRequest;
use App\Http\Requests\Admissions\SaveInquiryRequest;
use App\Http\Resources\AdmissionApplicationResource;
use App\Http\Resources\AdmissionInquiryResource;
use App\Models\AdmissionInquiry;
use App\Services\AdmissionService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionInquiryController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private AdmissionService $admissionService) {}

    public function index(Request $request): JsonResponse
    {
        $inquiries = $this->admissionService->inquiriesQuery($this->filters($request))->latest('inquiry_date')->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($inquiries, AdmissionInquiryResource::class, 'Inquiries retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->admissionService->inquiriesDatatable($this->filters($request)), 'Inquiries retrieved.');
    }

    public function store(SaveInquiryRequest $request): JsonResponse
    {
        return $this->successResponse(new AdmissionInquiryResource($this->admissionService->saveInquiry(null, $request->validated())), 'Inquiry recorded.', 201);
    }

    public function show(AdmissionInquiry $admissionInquiry): JsonResponse
    {
        return $this->successResponse(new AdmissionInquiryResource($admissionInquiry->load(['classSought:id,name', 'source:id,name'])), 'Inquiry retrieved.');
    }

    public function update(SaveInquiryRequest $request, AdmissionInquiry $admissionInquiry): JsonResponse
    {
        return $this->successResponse(new AdmissionInquiryResource($this->admissionService->saveInquiry($admissionInquiry, $request->validated())), 'Inquiry updated.');
    }

    public function destroy(AdmissionInquiry $admissionInquiry): JsonResponse
    {
        return $this->deleteWithApproval($admissionInquiry, 'Inquiry deleted.');
    }

    public function convert(SaveApplicationRequest $request, AdmissionInquiry $admissionInquiry): JsonResponse
    {
        $application = $this->admissionService->convertInquiry($admissionInquiry, $request->validated());

        return $this->successResponse(new AdmissionApplicationResource($application), "Application {$application->application_no} created from the inquiry.", 201);
    }

    /**
     * @return array{status: string|null}
     */
    private function filters(Request $request): array
    {
        return ['status' => is_string($request->input('status')) ? ($request->input('status') ?: null) : null];
    }
}
