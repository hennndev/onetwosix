<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AuthOtpException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordWithOtpRequest;
use App\Http\Requests\Api\VerifyAuthOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\AuthOtpChallenge;
use App\Models\CustomerUser;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use App\Services\AuthOtpService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        public AccurateService $accurateService,
        public AuthOtpService $authOtpService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $challenge = $this->authOtpService->issue(
                AuthOtpService::TYPE_REGISTER,
                $validated['phone'],
                $validated['name'],
                [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'phone' => $validated['phone'],
                    'birth_date' => $validated['birth_date'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'device_name' => $validated['device_name'] ?? 'mobile',
                ],
            );
        } catch (AuthOtpException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }

        return $this->success([
            'challenge_id' => $challenge->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ], 'Kode OTP registrasi telah dikirim ke WhatsApp.', 202);
    }

    public function verifyRegistration(VerifyAuthOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            /** @var array{user: User, device_name: string} $registration */
            $registration = $this->authOtpService->consume(
                $validated['challenge_id'],
                AuthOtpService::TYPE_REGISTER,
                $validated['otp'],
                function (AuthOtpChallenge $challenge): array {
                    /** @var array<string, mixed> $payload */
                    $payload = $challenge->payload;

                    if (User::query()->where('email', $payload['email'])->exists()
                        || UserProfile::query()->where('phone', $payload['phone'])->exists()) {
                        throw new AuthOtpException('Email atau nomor telepon sudah terdaftar.');
                    }

                    return [
                        'user' => $this->createCustomer($payload),
                        'device_name' => (string) ($payload['device_name'] ?? 'mobile'),
                    ];
                },
            );
        } catch (AuthOtpException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }

        $this->syncAccurateCustomer($registration['user']);

        $token = $registration['user']->createToken($registration['device_name'])->plainTextToken;

        $registration['user']->load(['profile', 'customerUser.tier']);

        return $this->success([
            'user' => new UserResource($registration['user']),
            'token' => $token,
        ], 'Registrasi dan verifikasi OTP berhasil.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $emailOrPhone = $validated['email'];

        $user = $this->findUserByIdentifier($emailOrPhone);

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->error('Email/nomor telepon atau password salah.', 401);
        }

        if (! $user->customerUser) {
            return $this->error('Akun ini bukan akun customer.', 403);
        }

        if (! $user->profile?->phone) {
            return $this->error('Nomor WhatsApp akun belum tersedia.', 422);
        }

        try {
            $challenge = $this->authOtpService->issue(
                AuthOtpService::TYPE_LOGIN,
                $user->profile->phone,
                $user->name,
                ['device_name' => $validated['device_name']],
                $user->id,
            );
        } catch (AuthOtpException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }

        return $this->success([
            'challenge_id' => $challenge->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ], 'Kode OTP login telah dikirim ke WhatsApp.', 202);
    }

    public function verifyLogin(VerifyAuthOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            /** @var array{user: User, device_name: string} $login */
            $login = $this->authOtpService->consume(
                $validated['challenge_id'],
                AuthOtpService::TYPE_LOGIN,
                $validated['otp'],
                function (AuthOtpChallenge $challenge): array {
                    $user = User::query()->with('customerUser')->find($challenge->user_id);

                    if (! $user?->customerUser) {
                        throw new AuthOtpException('Akun customer tidak ditemukan.');
                    }

                    return [
                        'user' => $user,
                        'device_name' => (string) ($challenge->payload['device_name'] ?? 'mobile'),
                    ];
                },
            );
        } catch (AuthOtpException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }

        $token = $login['user']->createToken($login['device_name'])->plainTextToken;
        $login['user']->load(['profile', 'customerUser.tier']);

        return $this->success([
            'user' => new UserResource($login['user']),
            'token' => $token,
        ], 'Login dan verifikasi OTP berhasil.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $identifier = $request->validated('email');
        $user = $this->findUserByIdentifier($identifier);

        if (! $user?->customerUser || ! $user->profile?->phone) {
            return $this->success([
                'challenge_id' => (string) Str::uuid(),
            ], 'Jika akun ditemukan, kode OTP reset password telah dikirim ke WhatsApp.', 202);
        }

        try {
            $challenge = $this->authOtpService->issue(
                AuthOtpService::TYPE_PASSWORD_RESET,
                $user->profile->phone,
                $user->name,
                userId: $user->id,
            );
        } catch (AuthOtpException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }

        return $this->success([
            'challenge_id' => $challenge->id,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ], 'Jika akun ditemukan, kode OTP reset password telah dikirim ke WhatsApp.', 202);
    }

    public function resetPassword(ResetPasswordWithOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->authOtpService->consume(
                $validated['challenge_id'],
                AuthOtpService::TYPE_PASSWORD_RESET,
                $validated['otp'],
                function (AuthOtpChallenge $challenge) use ($validated): User {
                    $user = User::query()->find($challenge->user_id);

                    if (! $user) {
                        throw new AuthOtpException('Akun customer tidak ditemukan.');
                    }

                    $user->forceFill([
                        'password' => Hash::make($validated['password']),
                        'remember_token' => Str::random(60),
                    ])->save();
                    $user->tokens()->delete();
                    event(new PasswordReset($user));

                    return $user;
                },
            );
        } catch (AuthOtpException $exception) {
            return $this->error($exception->getMessage(), $exception->status);
        }

        return $this->success(null, 'Password berhasil direset. Silakan login kembali.');
    }

    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $user->currentAccessToken()->delete();

        return $this->success(null, 'Logout berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token_firebase' => ['nullable', 'string', 'max:2048'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        if (array_key_exists('token_firebase', $validated) && $user->token_firebase !== $validated['token_firebase']) {
            $user->forceFill([
                'token_firebase' => $validated['token_firebase'],
            ])->save();
        }

        $user->load(['profile', 'customerUser.tier']);

        return $this->success([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCustomer(array $attributes): User
    {
        $user = User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
        ]);

        $profile = UserProfile::create([
            'user_id' => $user->id,
            'phone' => $attributes['phone'],
            'birth_date' => $attributes['birth_date'] ?? null,
            'address' => $attributes['address'] ?? null,
        ]);

        CustomerUser::create([
            'user_id' => $user->id,
            'user_profile_id' => $profile->id,
            'total_visits' => 0,
            'lifetime_spending' => 0,
            'tier_id' => Tier::query()->where('is_first_tier', true)->value('id'),
        ]);

        return $user;
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->with(['profile', 'customerUser'])
            ->where(function ($query) use ($identifier): void {
                $query->where('email', $identifier)
                    ->orWhereHas('profile', function ($profileQuery) use ($identifier): void {
                        $profileQuery->where('phone', $identifier);
                    });
            })
            ->first();
    }

    private function syncAccurateCustomer(User $user): void
    {
        try {
            $response = $this->accurateService->saveCustomer([
                'name' => $user->name,
                'email' => $user->email,
            ]);

            if (! empty($response['r']['id'])) {
                $user->customerUser->update([
                    'accurate_id' => $response['r']['id'],
                    'customer_code' => $response['r']['customerNo'] ?? null,
                ]);
            }
        } catch (\Exception $exception) {
            Log::warning('Accurate saveCustomer failed on register: '.$exception->getMessage());
        }
    }
}
