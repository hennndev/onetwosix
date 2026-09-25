<?php

namespace App\Actions\Settings;

use App\Models\QrisSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReplaceActiveQrisSetting
{
    public function handle(string $name, ?UploadedFile $image): QrisSetting
    {
        $existing = QrisSetting::query()->where('is_active', true)->first();
        $oldImagePath = $existing?->image_path;
        $newImagePath = $image?->store('qris', 'public');

        try {
            $qris = DB::transaction(function () use ($name, $newImagePath, $oldImagePath): QrisSetting {
                QrisSetting::query()->update(['is_active' => false]);

                return QrisSetting::create([
                    'name' => $name,
                    'image_path' => $newImagePath ?? $oldImagePath,
                    'is_active' => true,
                ]);
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        return $qris;
    }
}
