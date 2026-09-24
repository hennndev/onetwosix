<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardRedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'points_spent' => $this->points_spent,
            'status' => $this->status,
            'notes' => $this->notes,
            'reward' => new RewardResource($this->whenLoaded('reward')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
