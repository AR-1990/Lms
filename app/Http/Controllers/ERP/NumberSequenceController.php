<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\UpdateNumberSequenceRequest;
use App\Http\Resources\NumberSequenceResource;
use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Number formats are created by seeders (the code that issues them must exist),
 * so the API reads and edits them but never creates or deletes them.
 */
class NumberSequenceController extends Controller
{
    use ApiResponse;

    public function __construct(private NumberSequenceService $numberSequenceService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(NumberSequenceResource::collection($this->numberSequenceService->all())->resolve(), 'Number formats retrieved.');
    }

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->numberSequenceService->datatable(), 'Number formats retrieved.');
    }

    public function show(NumberSequence $numberSequence): JsonResponse
    {
        return $this->successResponse(new NumberSequenceResource($numberSequence), 'Number format retrieved.');
    }

    public function update(UpdateNumberSequenceRequest $request, NumberSequence $numberSequence): JsonResponse
    {
        $sequence = $this->numberSequenceService->update($numberSequence, $request->validated());

        return $this->successResponse(new NumberSequenceResource($sequence), 'Number format updated.');
    }
}
