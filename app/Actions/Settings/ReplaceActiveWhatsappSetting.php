<?php

namespace App\Actions\Settings;

use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\DB;

class ReplaceActiveWhatsappSetting
{
    /**
     * @param  array{phone_number: string, description?: string|null}  $attributes
     */
    public function handle(array $attributes): WhatsappSetting
    {
        return DB::transaction(function () use ($attributes): WhatsappSetting {
            WhatsappSetting::query()->update(['is_active' => false]);

            return WhatsappSetting::create([
                'phone_number' => $attributes['phone_number'],
                'description' => $attributes['description'] ?? null,
                'is_active' => true,
            ]);
        });
    }
}
