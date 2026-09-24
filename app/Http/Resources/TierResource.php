<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'name' => $this->name,
            'discount_percentage' => $this->discount_percentage,
            'minimum_spent' => $this->minimum_spent,
            'color' => $this->color,
        ];
    }
}
