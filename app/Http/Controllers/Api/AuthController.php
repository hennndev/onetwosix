<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\CustomerUser;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(public AccurateService $accurateService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $profile = UserProfile::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'],
                'birth_date' => $validated['birth_date'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            $firstTier = Tier::where('is_first_tier', true)->first();

            CustomerUser::create([
                'user_id' => $user->id,
                'user_profile_id' => $profile->id,
                'total_visits' => 0,
                'lifetime_spending' => 0,
                'tier_id' => $firstTier?->id,
            ]);

            return $user;
        });

        try {
            $response = $this->accurateService->saveCustomer([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if (! empty($response['r']['id'])) {
                $user->customerUser->update([
                    'accurate_id' => $response['r']['id'],
                    'customer_code' => $response['r']['customerNo'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Accurate saveCustomer failed on register: '.$e->getMessage());
        }

        $token = $user->createToken($request->input('device_name', 'mobile'))->plainTextToken;

        $user->load(['profile', 'customerUser.tier']);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Registrasi berhasil.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $emailOrPhone = $validated['email'];

        if (filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $emailOrPhone)->first();
        } else {
            $user = User::whereHas('customerUser')
                ->whereHas('profile', function ($query) use ($emailOrPhone) {
                    $query->where('phone', $emailOrPhone);
                })->first();
        }

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->error('Email/nomor telepon atau password salah.', 401);
        }

        if (! $user->customerUser) {
            return $this->error('Akun ini bukan akun customer.', 403);
        }

        $token = $user->createToken($validated['device_name'])->plainTextToken;

        $user->load(['profile', 'customerUser.tier']);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login berhasil.');
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
}
