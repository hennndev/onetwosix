<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total_visits' => $this->total_visits,
            'lifetime_spending' => (float) $this->lifetime_spending,
            'points' => $this->points,
            'tier' => new TierResource($this->whenLoaded('tier')),
        ];
    }
}
