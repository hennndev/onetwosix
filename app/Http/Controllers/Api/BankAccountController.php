<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBankAccountRequest;
use App\Http\Requests\Api\UpdateBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BankAccountController extends Controller
{
    use ApiResponse;

    /**
     * List all active bank accounts.
     */
    public function index(): JsonResponse
    {
        $bankAccounts = BankAccount::query()
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->get();

        return $this->success([
            'bank_accounts' => BankAccountResource::collection($bankAccounts),
        ]);
    }

    /**
     * Show a specific bank account.
     */
    public function show(BankAccount $bankAccount): JsonResponse
    {
        return $this->success([
            'bank_account' => new BankAccountResource($bankAccount),
        ]);
    }

    /**
     * Store a new bank account.
     */
    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $bankAccount = BankAccount::create($request->validated());

        return $this->success([
            'bank_account' => new BankAccountResource($bankAccount),
        ], 'Rekening berhasil ditambahkan.', 201);
    }

    /**
     * Update an existing bank account.
     */
    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $bankAccount->update($request->validated());

        return $this->success([
            'bank_account' => new BankAccountResource($bankAccount->fresh()),
        ], 'Rekening berhasil diperbarui.');
    }

    /**
     * Delete a bank account.
     */
    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        $bankAccount->delete();

        return $this->success(null, 'Rekening berhasil dihapus.');
    }
}
