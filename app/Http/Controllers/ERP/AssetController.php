<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\AssignAssetRequest;
use App\Http\Requests\Assets\CompleteRepairRequest;
use App\Http\Requests\Assets\DepreciationRequest;
use App\Http\Requests\Assets\DisposeAssetRequest;
use App\Http\Requests\Assets\ReturnAssetRequest;
use App\Http\Requests\Assets\SaveAssetCategoryRequest;
use App\Http\Requests\Assets\SaveAssetRequest;
use App\Http\Requests\Assets\SaveAuditRequest;
use App\Http\Requests\Assets\SaveLocationRequest;
use App\Http\Requests\Assets\SaveRepairRequest;
use App\Http\Requests\Assets\StartAuditRequest;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetCategory;
use App\Models\AssetMaintenance;
use App\Models\Employee;
use App\Models\Location;
use App\Services\AssetLedgerService;
use App\Services\AssetService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fixed assets API (PLAN.md §15): manage-assets for the register, repairs, verification, depreciation and
 * disposal; assign-assets for handing assets out and taking them back. Same API for web and mobile.
 */
class AssetController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private AssetService $assetService, private AssetLedgerService $ledgerService) {}

    /* ---------- Categories and locations ---------- */

    public function categoriesDatatable(): JsonResponse
    {
        return $this->successResponse($this->assetService->categoriesDatatable(), 'Asset categories retrieved.');
    }

    public function storeCategory(SaveAssetCategoryRequest $request): JsonResponse
    {
        return $this->successResponse($this->assetService->saveCategory(null, $request->validated()), 'Category added.', 201);
    }

    public function updateCategory(SaveAssetCategoryRequest $request, AssetCategory $assetCategory): JsonResponse
    {
        return $this->successResponse($this->assetService->saveCategory($assetCategory, $request->validated()), 'Category saved.');
    }

    public function destroyCategory(AssetCategory $assetCategory): JsonResponse
    {
        return $this->deleteWithApproval($assetCategory, 'Category deleted.');
    }

    public function locationsDatatable(): JsonResponse
    {
        return $this->successResponse($this->assetService->locationsDatatable(), 'Locations retrieved.');
    }

    public function storeLocation(SaveLocationRequest $request): JsonResponse
    {
        return $this->successResponse($this->assetService->saveLocation(null, $request->validated()), 'Location added.', 201);
    }

    public function updateLocation(SaveLocationRequest $request, Location $location): JsonResponse
    {
        return $this->successResponse($this->assetService->saveLocation($location, $request->validated()), 'Location saved.');
    }

    public function destroyLocation(Location $location): JsonResponse
    {
        return $this->deleteWithApproval($location, 'Location deleted.');
    }

    /* ---------- Register ---------- */

    public function datatable(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Asset::STATUSES)],
            'asset_category_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'holder_type' => ['nullable', Rule::in(array_keys(Asset::HOLDERS))],
            'holder_id' => ['nullable', 'integer'],
        ]);

        return $this->successResponse($this->assetService->datatable($filters), 'Assets retrieved.');
    }

    public function show(Asset $asset): JsonResponse
    {
        return $this->successResponse($this->assetService->details($asset), 'Asset retrieved.');
    }

    public function store(SaveAssetRequest $request): JsonResponse
    {
        return $this->successResponse($this->assetService->details($this->assetService->create($request->validated())), 'Asset registered.', 201);
    }

    public function update(SaveAssetRequest $request, Asset $asset): JsonResponse
    {
        return $this->successResponse($this->assetService->details($this->assetService->update($asset, $request->validated())), 'Asset saved.');
    }

    public function destroy(Asset $asset): JsonResponse
    {
        return $this->deleteWithApproval($asset, 'Asset deleted.');
    }

    /* ---------- Who holds what ---------- */

    public function holders(Request $request): JsonResponse
    {
        $validated = $request->validate(['type' => ['required', Rule::in(array_keys(Asset::HOLDERS))], 'term' => ['nullable', 'string', 'max:100']]);

        return $this->successResponse($this->assetService->holders($validated['type'], (string) ($validated['term'] ?? '')), 'Holders retrieved.');
    }

    public function assign(AssignAssetRequest $request, Asset $asset): JsonResponse
    {
        return $this->successResponse($this->assetService->details($this->assetService->assign($asset, $request->validated(), $request->user())), 'Asset handed over.');
    }

    public function takeBack(ReturnAssetRequest $request, Asset $asset): JsonResponse
    {
        return $this->successResponse($this->assetService->details($this->assetService->takeBack($asset, $request->validated(), $request->user())), 'Asset taken back.');
    }

    public function assignmentsDatatable(Request $request): JsonResponse
    {
        $filters = $request->validate(['assignee_type' => ['nullable', Rule::in(array_keys(Asset::HOLDERS))], 'open' => ['nullable', Rule::in(['', '1', '0'])]]);
        $open = ($filters['open'] ?? '') === '' ? null : $filters['open'] === '1';

        return $this->successResponse($this->assetService->assignmentsDatatable(['assignee_type' => $filters['assignee_type'] ?? null, 'open' => $open]), 'Hand-overs retrieved.');
    }

    /**
     * Assets the signed-in employee holds now (My HR on web and mobile).
     */
    public function mine(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        return $this->successResponse($employee === null ? [] : $this->assetService->heldBy($employee)->all(), 'Your assets retrieved.');
    }

    /* ---------- Repairs ---------- */

    public function maintenancesDatatable(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(AssetMaintenance::STATUSES)]])['status'] ?? null;

        return $this->successResponse($this->assetService->maintenancesDatatable($status), 'Repairs retrieved.');
    }

    public function reportRepair(SaveRepairRequest $request, Asset $asset): JsonResponse
    {
        return $this->successResponse($this->assetService->reportRepair($asset, $request->validated()), 'Repair reported; the asset is now in repair.', 201);
    }

    public function updateRepair(SaveRepairRequest $request, AssetMaintenance $assetMaintenance): JsonResponse
    {
        return $this->successResponse($this->assetService->updateRepair($assetMaintenance, $request->validated()), 'Repair saved.');
    }

    public function completeRepair(CompleteRepairRequest $request, AssetMaintenance $assetMaintenance): JsonResponse
    {
        return $this->successResponse($this->assetService->completeRepair($assetMaintenance, $request->validated(), $request->user()), 'Repair completed.');
    }

    /* ---------- Physical verification ---------- */

    public function auditsDatatable(): JsonResponse
    {
        return $this->successResponse($this->assetService->auditsDatatable(), 'Verifications retrieved.');
    }

    public function startAudit(StartAuditRequest $request): JsonResponse
    {
        return $this->successResponse($this->assetService->audit($this->assetService->startAudit($request->validated(), $request->user())), 'Verification started.', 201);
    }

    public function showAudit(AssetAudit $assetAudit): JsonResponse
    {
        return $this->successResponse($this->assetService->audit($assetAudit), 'Verification retrieved.');
    }

    public function saveAudit(SaveAuditRequest $request, AssetAudit $assetAudit): JsonResponse
    {
        $audit = $this->assetService->saveAudit($assetAudit, $request->validated('items', []), $request->boolean('complete'), $request->boolean('mark_missing_lost'), $request->user());

        return $this->successResponse($this->assetService->audit($audit), $audit->status === 'completed' ? 'Verification completed.' : 'Verification saved.');
    }

    /* ---------- Depreciation and disposal ---------- */

    public function depreciationPreview(DepreciationRequest $request): JsonResponse
    {
        return $this->successResponse($this->ledgerService->preview($request->validated('month').'-01'), 'Depreciation preview.');
    }

    public function depreciationRun(DepreciationRequest $request): JsonResponse
    {
        $entry = $this->ledgerService->run($request->validated('month').'-01', $request->user());

        return $this->successResponse(['voucher_no' => $entry?->voucher_no], "Depreciation posted as {$entry?->voucher_no}.");
    }

    public function depreciationReverse(DepreciationRequest $request): JsonResponse
    {
        $this->ledgerService->reverse($request->validated('month').'-01', $request->validated('reason'), $request->user());

        return $this->successResponse(null, 'Depreciation reversed.');
    }

    public function depreciationDatatable(): JsonResponse
    {
        return $this->successResponse($this->ledgerService->runsDatatable(), 'Depreciation runs retrieved.');
    }

    public function dispose(DisposeAssetRequest $request, Asset $asset): JsonResponse
    {
        $disposal = $this->ledgerService->dispose($asset, $request->validated(), $request->user());

        return $this->successResponse($this->assetService->details($asset->refresh()), 'Asset disposed'.($disposal->journal_entry_id ? ' and posted to the ledger.' : '.'));
    }

    public function disposalsDatatable(): JsonResponse
    {
        return $this->successResponse($this->ledgerService->disposalsDatatable(), 'Disposals retrieved.');
    }
}
