<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\ReplaceActiveQrisSetting;
use App\Actions\Settings\ReplaceActiveWhatsappSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveBankAccountRequest;
use App\Http\Requests\Settings\SavePaymentWhatsappRequest;
use App\Http\Requests\Settings\SaveQrisSettingRequest;
use App\Models\BankAccount;
use App\Models\QrisSetting;
use App\Models\WhatsappSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentSettingController extends Controller
{
    public function index(): View
    {
        return view('settings.payment.index', [
            'bankAccounts' => BankAccount::query()->orderBy('bank_name')->get(),
            'whatsapp' => WhatsappSetting::query()->where('is_active', true)->first(),
            'qris' => QrisSetting::query()->where('is_active', true)->first(),
        ]);
    }

    public function storeBankAccount(SaveBankAccountRequest $request): RedirectResponse
    {
        BankAccount::create($request->validated());

        return $this->redirectWithSuccess('Rekening berhasil ditambahkan!');
    }

    public function updateBankAccount(SaveBankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->update($request->validated());

        return $this->redirectWithSuccess('Rekening berhasil diperbarui!');
    }

    public function destroyBankAccount(BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->delete();

        return $this->redirectWithSuccess('Rekening berhasil dihapus!');
    }

    public function saveWhatsapp(
        SavePaymentWhatsappRequest $request,
        ReplaceActiveWhatsappSetting $replaceWhatsapp,
    ): RedirectResponse {
        $replaceWhatsapp->handle($request->validated());

        return $this->redirectWithSuccess('Nomor WhatsApp berhasil disimpan!');
    }

    public function saveQris(
        SaveQrisSettingRequest $request,
        ReplaceActiveQrisSetting $replaceQris,
    ): RedirectResponse {
        $replaceQris->handle(
            $request->validated('name'),
            $request->file('qris_image'),
        );

        return $this->redirectWithSuccess('QRIS berhasil disimpan!');
    }

    private function redirectWithSuccess(string $message): RedirectResponse
    {
        return redirect()->route('admin.settings.payment.index')->with('success', $message);
    }
}
