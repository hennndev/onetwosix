<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\DailyAuthCodeDeliveryMail;
use App\Models\DailyAuthCode;
use App\Models\GeneralSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class DailyAuthCodeController extends Controller
{
    public function index(): View
    {
        $today = now()->format('Y-m-d');
        $record = DailyAuthCode::forDate($today);

        return view('settings.daily-auth-code', [
            'activeCode' => $record->active_code,
            'autoCode' => $record->code,
            'isOverridden' => $record->override_code !== null,
            'generatedAt' => $record->generated_at?->format('H:i:s') ?? now()->format('H:i:s'),
            'today' => now()->translatedFormat('l, d F Y'),
        ]);
    }

    public function regenerate(): RedirectResponse
    {
        $today = now()->format('Y-m-d');
        $record = DailyAuthCode::forDate($today);
        $record->update([
            'code' => DailyAuthCode::generateRandom(),
            'override_code' => null,
            'generated_at' => now(),
        ]);

        return redirect()->route('admin.settings.daily-auth-code.index')
            ->with('success', 'Kode baru berhasil di-generate.');
    }

    public function override(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:4'],
        ]);

        $today = now()->format('Y-m-d');
        $record = DailyAuthCode::forDate($today);
        $record->update([
            'override_code' => $request->code,
            'generated_at' => now(),
        ]);

        return redirect()->route('admin.settings.daily-auth-code.index')
            ->with('success', 'Kode manual berhasil disimpan.');
    }

    public function clearOverride(): RedirectResponse
    {
        $today = now()->format('Y-m-d');
        $record = DailyAuthCode::forDate($today);
        $record->update(['override_code' => null]);

        return redirect()->route('admin.settings.daily-auth-code.index')
            ->with('success', 'Override dihapus. Kode otomatis aktif kembali.');
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'digits:4'],
        ]);

        $today = now()->format('Y-m-d');
        $record = DailyAuthCode::forDate($today);

        return response()->json([
            'valid' => $request->code === $record->active_code,
        ]);
    }

    public function sendEmail(Request $request, \App\Services\FonnteService $fonnteService): JsonResponse
    {
        $settings = GeneralSetting::instance();
        $targetEmail = trim((string) $settings->auth_code_target_email);
        $targetWhatsapp = trim((string) $settings->auth_code_target_whatsapp);
        $channel = $settings->auth_code_delivery_channel ?: 'both';

        $shouldSendEmail = in_array($channel, ['both', 'email'], true);
        $shouldSendWhatsapp = in_array($channel, ['both', 'whatsapp'], true);

        // Validation based on chosen channel
        if ($shouldSendEmail && $targetEmail === '' && $shouldSendWhatsapp && $targetWhatsapp === '') {
            return response()->json([
                'success' => false,
                'message' => 'Email atau Nomor WhatsApp tujuan auth code belum diatur di General Setting.',
            ], 422);
        } elseif ($shouldSendEmail && ! $shouldSendWhatsapp && $targetEmail === '') {
            return response()->json([
                'success' => false,
                'message' => 'Email tujuan auth code belum diatur di General Setting.',
            ], 422);
        } elseif ($shouldSendWhatsapp && ! $shouldSendEmail && $targetWhatsapp === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor WhatsApp tujuan auth code belum diatur di General Setting.',
            ], 422);
        }

        $record = DailyAuthCode::forDate(now()->format('Y-m-d'));
        $requestedBy = auth()->user()?->name ?? 'System';

        $emailSent = false;
        $whatsappSent = false;

        // Send Email if configured
        if ($shouldSendEmail && $targetEmail !== '') {
            try {
                if ($settings->mail_provider === 'resend') {
                    config(['mail.default' => 'resend']);
                } else {
                    config(['mail.default' => 'smtp']);
                }

                Mail::to($targetEmail)->send(new DailyAuthCodeDeliveryMail(
                    code: $record->active_code,
                    requestedBy: $requestedBy,
                    requestedAt: now()->format('d M Y H:i:s')
                ));
                $emailSent = true;
            } catch (\Exception $e) {
                // Log exception internally but don't halt whatsapp transmission
                report($e);
            }
        }

        // Send WhatsApp if configured
        if ($shouldSendWhatsapp && $targetWhatsapp !== '') {
            try {
                $whatsappSent = $fonnteService->sendOtp($targetWhatsapp, $record->active_code, $requestedBy);
            } catch (\Exception $e) {
                report($e);
            }
        }

        if ($shouldSendEmail && ! $emailSent && $shouldSendWhatsapp && ! $whatsappSent) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim auth code ke email maupun WhatsApp.',
            ], 500);
        } elseif ($shouldSendEmail && ! $emailSent && ! $shouldSendWhatsapp) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim auth code ke email.',
            ], 500);
        } elseif ($shouldSendWhatsapp && ! $whatsappSent && ! $shouldSendEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim auth code ke WhatsApp.',
            ], 500);
        }

        $channels = [];
        if ($emailSent) {
            $channels[] = 'Email';
        }
        if ($whatsappSent) {
            $channels[] = 'WhatsApp';
        }

        if ($request->input('source') === 'pos-selected-item-discount') {
            $request->session()->put('pos_discount_auth_code_requested_at', now()->timestamp);
        }
        if ($request->input('source') === 'booking-close-discount') {
            $request->session()->put('booking_discount_auth_code_requested_at', now()->timestamp);
        }

        return response()->json([
            'success' => true,
            'message' => 'Auth code berhasil dikirim ke '.implode(' & ', $channels).'.',
        ]);
    }
}
