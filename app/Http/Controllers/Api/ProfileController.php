<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $user->load(['profile', 'customerUser.tier']);

        return $this->success([
            'user' => new UserResource($user),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $user->update(['name' => $validated['name']]);
        }

        $profileData = array_filter([
            'phone' => $validated['phone'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'address' => $validated['address'] ?? null,
        ], fn ($value) => $value !== null);

        if ($request->hasFile('avatar')) {
            $profile = $user->profile;

            if ($profile?->avatar) {
                Storage::disk('public')->delete($profile->avatar);
            }

            $profileData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        if (! empty($profileData)) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
        }

        $user->load(['profile', 'customerUser.tier']);

        return $this->success([
            'user' => new UserResource($user),
        ], 'Profil berhasil diperbarui.');
    }
}
