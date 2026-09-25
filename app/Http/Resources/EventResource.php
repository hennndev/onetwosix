<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_active' => $this->is_active,
            'is_today' => $this->isToday(),
            'is_upcoming' => $this->isUpcoming(),
            'is_past' => $this->isPast(),
            'is_multi_day' => $this->isMultiDay(),
            'price_adjustment_type' => $this->price_adjustment_type,
            'price_adjustment_value' => (float) $this->price_adjustment_value,
            'price_adjustment_formatted' => $this->getPriceAdjustmentFormatted(),
        ];
    }
}
