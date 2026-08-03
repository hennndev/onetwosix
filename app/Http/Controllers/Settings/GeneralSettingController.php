<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\GeneralSetting;
use App\Models\Printer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GeneralSettingController extends Controller
{
    public function index(): View
    {
        $settings = GeneralSetting::instance();
        $printers = Printer::active()->orderBy('name')->get();
        $areas = Area::where('is_active', true)->orderBy('sort_order')->get();

        return view('settings.general-settings', compact('settings', 'printers', 'areas'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tax_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'service_charge_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'accurate_tax_account_no' => ['nullable', 'string', 'max:50'],
            'accurate_service_charge_account_no' => ['nullable', 'string', 'max:50'],
            'accurate_bank_account_no' => ['nullable', 'string', 'max:50'],
            'accurate_cash_account_no' => ['nullable', 'string', 'max:50'],
            'accurate_stock_warehouse_name' => ['nullable', 'string', 'max:255'],
            'can_choose_checker' => ['nullable', 'boolean'],
            'closed_billing_receipt_printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'walk_in_receipt_printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'end_day_receipt_printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'end_day_kitchen_printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'end_day_bar_printer_id' => ['nullable', 'integer', 'exists:printers,id'],
            'area_printer_settings' => ['nullable', 'array'],
            'area_printer_settings.*.*' => ['nullable', 'integer'],
            'mail_provider' => ['required', 'string', 'in:smtp,resend'],
            'auth_code_target_email' => ['nullable', 'email'],
            'auth_code_target_whatsapp' => ['nullable', 'string', 'max:20'],
            'fonnte_token' => ['nullable', 'string'],
            'auth_code_delivery_channel' => ['required', 'string', 'in:both,email,whatsapp'],
            'daily_auth_code_access_emails' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $emails = collect(preg_split('/[\r\n,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY))
                        ->map(fn (string $email): string => Str::lower(trim($email)))
                        ->filter();

                    foreach ($emails as $email) {
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $fail('Setiap email akses daily auth code harus valid.');

                            return;
                        }
                    }
                },
            ],
        ]);

        $validated['can_choose_checker'] = $request->boolean('can_choose_checker');
        $validated['daily_auth_code_access_emails'] = $this->normalizeEmailList(
            $validated['daily_auth_code_access_emails'] ?? null
        );

        GeneralSetting::instance()->update($validated);

        return redirect()->route('admin.settings.general.index')
            ->with('success', 'Pengaturan umum berhasil disimpan.');
    }

    private function normalizeEmailList(?string $emails): ?string
    {
        if ($emails === null) {
            return null;
        }

        $normalizedEmails = collect(preg_split('/[\r\n,]+/', $emails, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        return $normalizedEmails->isEmpty()
            ? null
            : $normalizedEmails->implode("\n");
    }

    public function sendTestEmail(): RedirectResponse
    {
        $settings = GeneralSetting::instance();

        if (empty($settings->auth_code_target_email)) {
            return back()->with('error', 'Gagal mengirim test email: Email Tujuan Auth Code belum diisi.');
        }

        try {
            if ($settings->mail_provider === 'resend') {
                config(['mail.default' => 'resend']);
            } else {
                config(['mail.default' => 'smtp']);
            }

            \Illuminate\Support\Facades\Mail::to($settings->auth_code_target_email)->send(new \App\Mail\TestMail(
                requestedBy: auth()->user()?->name ?? 'Administrator',
                requestedAt: now()->format('d M Y H:i:s')
            ));

            return back()->with('success', 'Email percobaan berhasil dikirim ke '.$settings->auth_code_target_email.' menggunakan '.strtoupper($settings->mail_provider).'. Silakan cek kotak masuk Anda.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengirim email: '.$e->getMessage());
        }
    }
}
