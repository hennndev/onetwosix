<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentCustomerUserId = $request->user()?->customerUser?->id;

        return [
            'id' => $this->id,
            'song_title' => $this->song_title,
            'artist' => $this->artist,
            'tip' => (float) $this->tip,
            'status' => $this->status,
            'is_mine' => $currentCustomerUserId !== null && $this->customer_user_id === $currentCustomerUserId,
            'user' => [
                'id' => $this->customerUser?->user?->id,
                'name' => $this->customerUser?->user?->name,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
