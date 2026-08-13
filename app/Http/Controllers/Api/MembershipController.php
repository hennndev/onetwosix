<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TierResource;
use App\Models\Tier;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class MembershipController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $tiers = Tier::orderBy('level')->get();

        return $this->success([
            'tiers' => TierResource::collection($tiers),
        ]);
    }

    public function myMembership(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $customerUser = $user->customerUser;

        if (! $customerUser) {
            return $this->error('Akun customer tidak ditemukan.', 403);
        }

        $customerUser->load('tier');

        $allTiers = Tier::orderBy('level')->get();

        $currentTier = $customerUser->tier;
        $nextTier = $allTiers->where('level', '>', $currentTier?->level ?? 0)->first();

        $spendingToNextTier = $nextTier
            ? max(0, $nextTier->minimum_spent - $customerUser->lifetime_spending)
            : 0;

        return $this->success([
            'membership' => [
                'current_tier' => $currentTier ? new TierResource($currentTier) : null,
                'next_tier' => $nextTier ? new TierResource($nextTier) : null,
                'points' => $customerUser->points,
                'lifetime_spending' => (float) $customerUser->lifetime_spending,
                'total_visits' => $customerUser->total_visits,
                'spending_to_next_tier' => (float) $spendingToNextTier,
            ],
            'all_tiers' => TierResource::collection($allTiers),
        ]);
    }

    public function qrCode(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $customerUser = $user->customerUser;

        if (! $customerUser) {
            return $this->error('Akun customer tidak ditemukan.', 403);
        }

        return $this->success([
            'qr_data' => [
                'type' => 'member',
                'user_id' => $user->id,
                'customer_user_id' => $customerUser->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
