<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerUser */
class LeaderboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rank' => $this->rank ?? null,
            'customer_user_id' => $this->id,
            'name' => $this->user?->name,
            'avatar' => $this->profile?->avatar ? asset('storage/'.$this->profile->avatar) : null,
            'total_visits' => $this->total_visits,
            'lifetime_spending' => (float) $this->lifetime_spending,
            'period_spending' => (float) ($this->period_spending ?? 0),
            'period_visits' => (int) ($this->period_visits ?? 0),
            'points' => $this->points,
            'tier' => new TierResource($this->whenLoaded('tier')),
        ];
    }
}
