<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DisplayMessageRequest */
class DisplayMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;

        return [
            'id' => $this->id,
            'message' => $this->message,
            'tip' => (int) $this->tip,
            'status' => $this->status,
            'is_mine' => $currentUserId !== null && $this->customer_id === $currentUserId,
            'user' => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
