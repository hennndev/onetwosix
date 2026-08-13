<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RedeemRewardRequest;
use App\Http\Resources\RewardRedemptionResource;
use App\Http\Resources\RewardResource;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $rewards = Reward::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderBy('points_required')
            ->get();

        return $this->success([
            'rewards' => RewardResource::collection($rewards),
        ]);
    }

    public function show(Reward $reward): JsonResponse
    {
        return $this->success([
            'reward' => new RewardResource($reward),
        ]);
    }

    public function redeem(RedeemRewardRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->customerUser) {
            return $this->error('Akun customer tidak ditemukan.', 403);
        }

        $reward = Reward::findOrFail($request->validated('reward_id'));

        if (! $reward->is_active || $reward->stock <= 0) {
            return $this->error('Reward tidak tersedia.', 422);
        }

        $customerPoints = $user->customerUser->points;

        if ($customerPoints < $reward->points_required) {
            return $this->error('Poin tidak mencukupi. Dibutuhkan '.$reward->points_required.' poin, kamu memiliki '.$customerPoints.' poin.', 422);
        }

        $redemption = DB::transaction(function () use ($user, $reward, $request) {
            $redemption = RewardRedemption::create([
                'customer_user_id' => $user->customerUser->id,
                'reward_id' => $reward->id,
                'points_spent' => $reward->points_required,
                'status' => 'pending',
                'notes' => $request->validated('notes'),
            ]);

            $reward->increment('redeemed_count');
            $reward->decrement('stock');

            return $redemption;
        });

        $redemption->load('reward');

        return $this->success([
            'redemption' => new RewardRedemptionResource($redemption),
        ], 'Reward berhasil di-redeem.', 201);
    }

    public function myRedemptions(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->customerUser) {
            return $this->success(['redemptions' => []]);
        }

        $redemptions = RewardRedemption::with('reward')
            ->where('customer_user_id', $user->customerUser->id)
            ->latest()
            ->get();

        return $this->success([
            'redemptions' => RewardRedemptionResource::collection($redemptions),
        ]);
    }
}
