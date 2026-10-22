<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\ReverseJournalEntryRequest;
use App\Http\Requests\Accounts\SaveJournalEntryRequest;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\JournalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalEntryController extends Controller
{
    use ApiResponse;

    public function __construct(private JournalService $journalService) {}

    public function index(Request $request): JsonResponse
    {
        $entries = $this->journalService->query($this->filters($request))->latest('entry_date')->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return $this->paginatedResponse($entries, JournalEntryResource::class, 'Vouchers retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->journalService->datatable($this->filters($request)), 'Vouchers retrieved.');
    }

    public function store(SaveJournalEntryRequest $request): JsonResponse
    {
        return $this->successResponse(new JournalEntryResource($this->journalService->createDraft($request->validated())), 'Voucher saved as draft.', 201);
    }

    public function show(JournalEntry $voucher): JsonResponse
    {
        return $this->successResponse(new JournalEntryResource($this->journalService->find($voucher)), 'Voucher retrieved.');
    }

    public function update(SaveJournalEntryRequest $request, JournalEntry $voucher): JsonResponse
    {
        return $this->successResponse(new JournalEntryResource($this->journalService->updateDraft($voucher, $request->validated())), 'Draft updated.');
    }

    public function destroy(JournalEntry $voucher): JsonResponse
    {
        $this->journalService->deleteDraft($voucher);

        return $this->successResponse(null, 'Draft deleted.');
    }

    public function post(JournalEntry $voucher): JsonResponse
    {
        return $this->successResponse(new JournalEntryResource($this->journalService->post($voucher)), 'Voucher posted to the ledger.');
    }

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $voucher): JsonResponse
    {
        $reversal = $this->journalService->reverse($voucher, $request->validated('reason'), $request->validated('entry_date'));

        return $this->successResponse(new JournalEntryResource($reversal), "Voucher reversed by {$reversal->voucher_no}.", 201);
    }

    /**
     * @return array{status: string|null, voucher_type_id: int|null, date_from: string|null, date_to: string|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([JournalEntry::STATUS_DRAFT, JournalEntry::STATUS_POSTED, JournalEntry::STATUS_REVERSED])],
            'voucher_type_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            'status' => $validated['status'] ?? null,
            'voucher_type_id' => isset($validated['voucher_type_id']) ? (int) $validated['voucher_type_id'] : null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];
    }
}
