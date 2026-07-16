<?php

namespace App\Services;

use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    public function sendOtp(string $targetPhone, string $otpCode, string $requestedBy): bool
    {
        $settings = GeneralSetting::instance();
        $token = env('FONNTE_TOKEN') ?: $settings->fonnte_token;

        if (empty($token) || empty($targetPhone)) {
            Log::warning('Fonnte sendOtp skipped: token or target phone is missing.', [
                'has_token' => ! empty($token),
                'target_phone' => $targetPhone,
            ]);

            return false;
        }

        $message = "🔑 *KODE OTP OTORISASI POS 126 CLUB*\n\n".
                   "Kode OTP Anda: *{$otpCode}*\n\n".
                   "• Diminta oleh: {$requestedBy}\n".
                   '• Waktu: '.now()->format('d M Y H:i:s')."\n\n".
                   '_Harap rahasiakan kode ini dari siapapun._';

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://api.fonnte.com/send', [
                'target' => $targetPhone,
                'message' => $message,
            ]);

            $data = $response->json();

            if (! $response->successful() || (is_array($data) && isset($data['status']) && $data['status'] === false)) {
                Log::error('Fonnte API returned error: '.($data['reason'] ?? $response->body()));

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Fonnte HTTP request failed: '.$e->getMessage());

            return false;
        }
    }
}
