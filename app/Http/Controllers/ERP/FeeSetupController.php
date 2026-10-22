<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\SaveConcessionTypeRequest;
use App\Http\Requests\Fees\SaveFeeHeadRequest;
use App\Http\Requests\Fees\SaveFeeStructureRequest;
use App\Http\Requests\Fees\SaveStudentConcessionRequest;
use App\Http\Resources\ConcessionTypeResource;
use App\Http\Resources\FeeHeadResource;
use App\Http\Resources\FeeStructureResource;
use App\Http\Resources\StudentConcessionResource;
use App\Models\ConcessionType;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\StudentConcession;
use App\Services\FeeSetupService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fee heads, concession types, class fee structures and student concessions.
 */
class FeeSetupController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private FeeSetupService $feeSetupService) {}

    /* ---------- Fee heads ---------- */

    public function heads(): JsonResponse
    {
        return $this->successResponse(FeeHeadResource::collection($this->feeSetupService->headsQuery()->orderBy('sort_order')->orderBy('name')->get())->resolve(), 'Fee heads retrieved.');
    }

    public function headsDatatable(): JsonResponse
    {
        return $this->successResponse($this->feeSetupService->headsDatatable(), 'Fee heads retrieved.');
    }

    public function storeHead(SaveFeeHeadRequest $request): JsonResponse
    {
        return $this->successResponse(new FeeHeadResource($this->feeSetupService->saveHead(null, $request->validated())), 'Fee head created.', 201);
    }

    public function updateHead(SaveFeeHeadRequest $request, FeeHead $feeHead): JsonResponse
    {
        return $this->successResponse(new FeeHeadResource($this->feeSetupService->saveHead($feeHead, $request->validated())), 'Fee head updated.');
    }

    public function destroyHead(FeeHead $feeHead): JsonResponse
    {
        return $this->deleteWithApproval($feeHead, 'Fee head deleted.');
    }

    /* ---------- Concession types ---------- */

    public function concessionTypes(): JsonResponse
    {
        return $this->successResponse(ConcessionTypeResource::collection($this->feeSetupService->concessionTypesQuery()->orderBy('name')->get())->resolve(), 'Concession types retrieved.');
    }

    public function concessionTypesDatatable(): JsonResponse
    {
        return $this->successResponse($this->feeSetupService->concessionTypesDatatable(), 'Concession types retrieved.');
    }

    public function storeConcessionType(SaveConcessionTypeRequest $request): JsonResponse
    {
        return $this->successResponse(new ConcessionTypeResource($this->feeSetupService->saveConcessionType(null, $request->validated())), 'Concession type created.', 201);
    }

    public function updateConcessionType(SaveConcessionTypeRequest $request, ConcessionType $concessionType): JsonResponse
    {
        return $this->successResponse(new ConcessionTypeResource($this->feeSetupService->saveConcessionType($concessionType, $request->validated())), 'Concession type updated.');
    }

    public function destroyConcessionType(ConcessionType $concessionType): JsonResponse
    {
        return $this->deleteWithApproval($concessionType, 'Concession type deleted.');
    }

    /* ---------- Fee structures ---------- */

    public function structuresDatatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->feeSetupService->structuresDatatable(['academic_session_id' => $request->integer('academic_session_id') ?: null]), 'Fee structures retrieved.');
    }

    public function showStructure(FeeStructure $feeStructure): JsonResponse
    {
        return $this->successResponse(new FeeStructureResource($this->feeSetupService->findStructure($feeStructure)), 'Fee structure retrieved.');
    }

    public function storeStructure(SaveFeeStructureRequest $request): JsonResponse
    {
        return $this->successResponse(new FeeStructureResource($this->feeSetupService->saveStructure(null, $request->validated())), 'Fee structure created.', 201);
    }

    public function updateStructure(SaveFeeStructureRequest $request, FeeStructure $feeStructure): JsonResponse
    {
        return $this->successResponse(new FeeStructureResource($this->feeSetupService->saveStructure($feeStructure, $request->validated())), 'Fee structure updated.');
    }

    public function destroyStructure(FeeStructure $feeStructure): JsonResponse
    {
        return $this->deleteWithApproval($feeStructure, 'Fee structure deleted.');
    }

    public function copyStructures(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage-fee-setup'), 403);

        $validated = $request->validate([
            'from_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
            'to_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->whereNull('deleted_at')],
        ]);

        $copied = $this->feeSetupService->copyStructures((int) $validated['from_session_id'], (int) $validated['to_session_id']);

        return $this->successResponse(['copied' => $copied], $copied === 0 ? 'Nothing new to copy.' : "{$copied} fee structures copied.");
    }

    /* ---------- Student concessions ---------- */

    public function concessionsDatatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->feeSetupService->concessionsDatatable([
            'student_id' => $request->integer('student_id') ?: null,
            'status' => is_string($request->input('status')) ? ($request->input('status') ?: null) : null,
        ]), 'Concessions retrieved.');
    }

    public function storeConcession(SaveStudentConcessionRequest $request): JsonResponse
    {
        $concession = $this->feeSetupService->grantConcession($request->validated());

        return $this->successResponse(new StudentConcessionResource($concession), $concession->status === 'approved' ? 'Concession granted.' : 'Concession saved; it applies once approved.', 201);
    }

    public function decideConcession(Request $request, StudentConcession $studentConcession): JsonResponse
    {
        abort_unless($request->user()->hasPermission('manage-fee-setup'), 403);

        $validated = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])]]);

        return $this->successResponse(new StudentConcessionResource($this->feeSetupService->decideConcession($studentConcession, $validated['decision'])), 'Concession '.$validated['decision'].'.');
    }

    public function destroyConcession(StudentConcession $studentConcession): JsonResponse
    {
        return $this->deleteWithApproval($studentConcession, 'Concession removed.');
    }
}
