<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\SaveAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use ApiResponse;

    public function __construct(private AccountService $accountService) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $this->accountService->query($this->filters($request))->orderBy('code')->paginate(min(max($request->integer('per_page', 50), 1), 200));

        return $this->paginatedResponse($accounts, AccountResource::class, 'Accounts retrieved.');
    }

    public function datatable(Request $request): JsonResponse
    {
        return $this->successResponse($this->accountService->datatable($this->filters($request)), 'Accounts retrieved.');
    }

    public function options(): JsonResponse
    {
        return $this->successResponse($this->accountService->options(), 'Account options retrieved.');
    }

    public function store(SaveAccountRequest $request): JsonResponse
    {
        return $this->successResponse(new AccountResource($this->accountService->create($request->validated())), 'Account created.', 201);
    }

    public function show(Account $account): JsonResponse
    {
        return $this->successResponse(new AccountResource($this->accountService->find($account)), 'Account retrieved.');
    }

    public function update(SaveAccountRequest $request, Account $account): JsonResponse
    {
        return $this->successResponse(new AccountResource($this->accountService->update($account, $request->validated())), 'Account updated.');
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->accountService->delete($account);

        return $this->successResponse(null, 'Account deleted.');
    }

    /**
     * Ledger lines of one account (Yajra grid) with opening / closing balance in `summary`.
     */
    public function ledger(Request $request, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return $this->successResponse($this->accountService->ledger($account, $validated['from'] ?? null, $validated['to'] ?? null), 'Ledger retrieved.');
    }

    public function trialBalance(Request $request): JsonResponse
    {
        $validated = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d']]);

        return $this->successResponse($this->accountService->trialBalance($validated['as_of'] ?? now()->toDateString()), 'Trial balance retrieved.');
    }

    /**
     * @return array{account_type_id: int|null, is_active: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'account_type_id' => $request->integer('account_type_id') ?: null,
            'is_active' => is_scalar($request->input('is_active')) ? (string) $request->input('is_active') : null,
        ];
    }
}
