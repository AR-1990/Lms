<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounts\SaveBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use App\Services\BankAccountService;
use App\Traits\ApiResponse;
use App\Traits\DeletesWithApproval;
use Illuminate\Http\JsonResponse;

class BankAccountController extends Controller
{
    use ApiResponse, DeletesWithApproval;

    public function __construct(private BankAccountService $bankAccountService) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(BankAccountResource::collection($this->bankAccountService->query()->orderBy('account_title')->get())->resolve(), 'Bank accounts retrieved.');
    }

    public function datatable(): JsonResponse
    {
        return $this->successResponse($this->bankAccountService->datatable(), 'Bank accounts retrieved.');
    }

    public function store(SaveBankAccountRequest $request): JsonResponse
    {
        return $this->successResponse(new BankAccountResource($this->bankAccountService->create($request->validated())), 'Bank account created.', 201);
    }

    public function show(BankAccount $bankAccount): JsonResponse
    {
        return $this->successResponse(new BankAccountResource($bankAccount->load(['account:id,code,name', 'bank:id,name'])), 'Bank account retrieved.');
    }

    public function update(SaveBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        return $this->successResponse(new BankAccountResource($this->bankAccountService->update($bankAccount, $request->validated())), 'Bank account updated.');
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        return $this->deleteWithApproval($bankAccount, 'Bank account deleted.');
    }
}
