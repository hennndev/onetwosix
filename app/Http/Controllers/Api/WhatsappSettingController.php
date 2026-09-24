<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveWhatsappSettingRequest;
use App\Http\Resources\WhatsappSettingResource;
use App\Models\WhatsappSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class WhatsappSettingController extends Controller
{
    use ApiResponse;

    /**
     * Get the active WhatsApp confirmation number.
     */
    public function show(): JsonResponse
    {
        $whatsapp = WhatsappSetting::query()
            ->where('is_active', true)
            ->first();

        if (! $whatsapp) {
            return $this->error('Nomor WhatsApp belum diatur.', 404);
        }

        return $this->success([
            'whatsapp' => new WhatsappSettingResource($whatsapp),
        ]);
    }

    /**
     * Save (create or update) the WhatsApp confirmation number.
     * Only one active number is allowed.
     */
    public function save(SaveWhatsappSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Deactivate all existing entries
        WhatsappSetting::query()->update(['is_active' => false]);

        // Create a new active entry
        $whatsapp = WhatsappSetting::create([
            'phone_number' => $validated['phone_number'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return $this->success([
            'whatsapp' => new WhatsappSettingResource($whatsapp),
        ], 'Nomor WhatsApp berhasil disimpan.', 201);
    }
}
