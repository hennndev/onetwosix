<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankAccountResource;
use App\Http\Resources\QrisSettingResource;
use App\Http\Resources\WhatsappSettingResource;
use App\Models\BankAccount;
use App\Models\QrisSetting;
use App\Models\WhatsappSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PaymentInfoController extends Controller
{
    use ApiResponse;

    /**
     * Get all active bank accounts, active QRIS, and the active WhatsApp confirmation number.
     */
    public function index(): JsonResponse
    {
        $bankAccounts = BankAccount::query()
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->get();

        $whatsapp = WhatsappSetting::query()
            ->where('is_active', true)
            ->first();

        $qris = QrisSetting::query()
            ->where('is_active', true)
            ->first();

        return $this->success([
            'bank_accounts' => BankAccountResource::collection($bankAccounts),
            'qris' => $qris ? new QrisSettingResource($qris) : null,
            'whatsapp' => $whatsapp ? new WhatsappSettingResource($whatsapp) : null,
        ]);
    }
}
