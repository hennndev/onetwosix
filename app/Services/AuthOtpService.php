<?php

namespace App\Services;

use App\Exceptions\AuthOtpException;
use App\Models\AuthOtpChallenge;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthOtpService
{
    public const TYPE_LOGIN = 'login';

    public const TYPE_REGISTER = 'register';

    public const TYPE_PASSWORD_RESET = 'password_reset';

    private const EXPIRATION_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    public function __construct(public FonnteService $fonnteService) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function issue(
        string $type,
        string $targetPhone,
        string $requestedBy,
        array $payload = [],
        ?int $userId = null,
    ): AuthOtpChallenge {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $challenge = AuthOtpChallenge::create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'user_id' => $userId,
            'target_phone' => $targetPhone,
            'payload' => $payload,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
        ]);

        $sent = $this->fonnteService->sendAuthenticationOtp(
            $targetPhone,
            $code,
            $requestedBy,
            $this->purposeLabel($type),
        );

        if (! $sent) {
            $challenge->delete();

            throw new AuthOtpException('Kode OTP gagal dikirim ke WhatsApp. Silakan coba lagi.', 503);
        }

        AuthOtpChallenge::query()
            ->whereKeyNot($challenge->id)
            ->where('type', $type)
            ->where('target_phone', $targetPhone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        return $challenge;
    }

    /**
     * @template TResult
     *
     * @param  Closure(AuthOtpChallenge): TResult  $callback
     * @return TResult
     */
    public function consume(string $challengeId, string $type, string $code, Closure $callback): mixed
    {
        $failureMessage = null;

        $result = DB::transaction(function () use ($challengeId, $type, $code, $callback, &$failureMessage): mixed {
            $challenge = AuthOtpChallenge::query()
                ->lockForUpdate()
                ->find($challengeId);

            if (! $challenge || $challenge->type !== $type) {
                $failureMessage = 'Challenge OTP tidak valid.';

                return null;
            }

            if ($challenge->consumed_at) {
                $failureMessage = 'Kode OTP sudah pernah digunakan.';

                return null;
            }

            if ($challenge->expires_at->isPast()) {
                $failureMessage = 'Kode OTP sudah kedaluwarsa.';

                return null;
            }

            if ($challenge->attempts >= self::MAX_ATTEMPTS) {
                $failureMessage = 'Batas percobaan OTP telah tercapai.';

                return null;
            }

            if (! Hash::check($code, $challenge->code_hash)) {
                $challenge->increment('attempts');
                $failureMessage = 'Kode OTP tidak valid.';

                return null;
            }

            $value = $callback($challenge);
            $challenge->update(['consumed_at' => now()]);

            return $value;
        });

        if ($failureMessage !== null) {
            throw new AuthOtpException($failureMessage);
        }

        return $result;
    }

    private function purposeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_LOGIN => 'login akun',
            self::TYPE_REGISTER => 'registrasi akun',
            self::TYPE_PASSWORD_RESET => 'reset password',
            default => 'verifikasi akun',
        };
    }
}
