<?php

namespace App\Http\Controllers;

use App\Models\CustomerUser;
use App\Models\Reward;
use App\Models\RewardRedemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $rewards = Reward::orderBy('points_required')->get();
        $redemptions = RewardRedemption::with(['reward', 'customerUser.user.profile', 'processor'])
            ->latest()
            ->get();
        $customerUsers = CustomerUser::with(['user.profile'])->whereHas('user')->get();

        $totalRewards = $rewards->count();
        $totalStock = $rewards->sum('stock');
        $totalPointsValue = $rewards->sum(fn ($r) => $r->points_required * $r->stock);
        $totalRedeemed = $rewards->sum('redeemed_count');

        return view('rewards.index', compact(
            'rewards',
            'redemptions',
            'customerUsers',
            'totalRewards',
            'totalStock',
            'totalPointsValue',
            'totalRedeemed'
        ));
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:drink,voucher,vip',
            'description' => 'nullable|string',
            'points_required' => 'required|integer|min:1',
            'stock' => 'required|integer|min:0',
        ]);

        Reward::create($validated);

        return redirect()->route('admin.rewards.index')->with('success', 'Reward berhasil ditambahkan!');
    }

    public function update(Request $request, Reward $reward): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:drink,voucher,vip',
            'description' => 'nullable|string',
            'points_required' => 'required|integer|min:1',
            'stock' => 'required|integer|min:0',
        ]);

        $reward->update($validated);

        return redirect()->route('admin.rewards.index')->with('success', 'Reward berhasil diupdate!');
    }

    public function destroy(Reward $reward): \Illuminate\Http\RedirectResponse
    {
        $reward->delete();

        return redirect()->route('admin.rewards.index')->with('success', 'Reward berhasil dihapus!');
    }

    public function redeem(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'customer_user_id' => 'required|exists:customer_users,id',
            'reward_id' => 'required|exists:rewards,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $reward = Reward::findOrFail($validated['reward_id']);
        $customerUser = CustomerUser::findOrFail($validated['customer_user_id']);
        $quantity = (int) $validated['quantity'];
        $totalPointsSpent = $reward->points_required * $quantity;

        if ($reward->stock < $quantity) {
            return back()->withErrors(['error' => "Stok reward '{$reward->name}' tidak mencukupi (Sisa stok: {$reward->stock})."])->withInput();
        }

        if ($customerUser->points < $totalPointsSpent) {
            return back()->withErrors(['error' => "Poin customer ({$customerUser->points} pts) tidak mencukupi untuk penukaran ini (Dibutuhkan: {$totalPointsSpent} pts)."])->withInput();
        }

        DB::transaction(function () use ($reward, $customerUser, $quantity, $totalPointsSpent, $validated, $request) {
            RewardRedemption::create([
                'reward_id' => $reward->id,
                'customer_user_id' => $customerUser->id,
                'points_spent' => $totalPointsSpent,
                'quantity' => $quantity,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
                'processed_by' => $request->user()?->id,
            ]);

            $reward->decrement('stock', $quantity);
            $reward->increment('redeemed_count', $quantity);
        });

        return redirect()->route('admin.rewards.index')->with('success', 'Penukaran reward manual berhasil diproses!');
    }

    public function cancelRedemption(RewardRedemption $redemption): \Illuminate\Http\RedirectResponse
    {
        DB::transaction(function () use ($redemption) {
            $reward = $redemption->reward;
            if ($reward) {
                $reward->increment('stock', $redemption->quantity);
                if ($reward->redeemed_count >= $redemption->quantity) {
                    $reward->decrement('redeemed_count', $redemption->quantity);
                }
            }

            $redemption->delete();
        });

        return redirect()->route('admin.rewards.index')->with('success', 'Penukaran reward berhasil dibatalkan dan stok telah dipulihkan!');
    }
}
