<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerUser extends Model
{
    protected $guarded;

    protected $casts = [
        'total_visits' => 'integer',
        'lifetime_spending' => 'decimal:2',
        'points' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profile()
    {
        return $this->belongsTo(UserProfile::class, 'user_profile_id');
    }

    public function tier()
    {
        return $this->belongsTo(Tier::class);
    }

    public function rewardRedemptions()
    {
        return $this->hasMany(RewardRedemption::class);
    }

    // Calculate points (1 point per 10,000 spent, minus redeemed points)
    public function getPointsAttribute(): int
    {
        $earnedPoints = (int) floor($this->lifetime_spending / 10000);
        $spentPoints = (int) $this->rewardRedemptions()
            ->whereIn('status', ['pending', 'completed'])
            ->sum('points_spent');

        return max(0, $earnedPoints - $spentPoints);
    }
}
