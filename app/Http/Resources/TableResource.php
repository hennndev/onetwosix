<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Tabel */
class TableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table_number' => $this->table_number,
            'capacity' => $this->capacity,
            'minimum_charge' => (float) $this->minimum_charge,
            'status' => $this->status,
            'area' => new AreaResource($this->whenLoaded('area')),
        ];
    }
}
