<?php

namespace App\Actions\Areas;

use App\Models\Area;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SaveArea
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, ?Area $area = null): Area
    {
        $image = $attributes['image'] ?? null;
        unset($attributes['image']);

        $newImagePath = $image instanceof UploadedFile
            ? $image->store('areas', 'public')
            : null;
        $oldImagePath = $area?->image;

        if ($newImagePath) {
            $attributes['image'] = $newImagePath;
        }

        try {
            if ($area) {
                $area->update($attributes);
            } else {
                $area = Area::create($attributes);
            }
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return $area;
    }
}
