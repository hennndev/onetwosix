<?php

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SaveEvent
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, ?Event $event = null): Event
    {
        $image = $attributes['image'] ?? null;
        unset($attributes['image']);

        $newImagePath = $image instanceof UploadedFile
            ? $image->store('events', 'public')
            : null;
        $oldImagePath = $event?->image;

        if ($newImagePath) {
            $attributes['image'] = $newImagePath;
        }

        try {
            if ($event) {
                $event->update($attributes);
            } else {
                $event = Event::create($attributes);
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

        return $event;
    }
}
