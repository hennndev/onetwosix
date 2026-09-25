<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'category' => $this->category,
            'category_label' => $this->category_label,
            'description' => $this->description,
            'points_required' => $this->points_required,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'is_redeemable' => $this->is_active && $this->stock > 0,
        ];
    }
}
