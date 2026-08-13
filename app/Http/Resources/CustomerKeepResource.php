<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerKeepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_name' => $this->item_name,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'status' => $this->status,
            'is_active_today' => $this->is_active_today,
            'stored_at' => $this->stored_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
        ];
    }
}
