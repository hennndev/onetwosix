<?php

namespace App\Observers;

use App\Models\SongRequest;
use App\Services\SessionTipService;

class SongRequestObserver
{
    public function __construct(protected SessionTipService $sessionTipService) {}

    public function created(SongRequest $songRequest): void
    {
        $this->sessionTipService->sync($songRequest->table_session_id);
    }

    public function updated(SongRequest $songRequest): void
    {
        if (! $songRequest->wasChanged(['status', 'tip', 'table_session_id'])) {
            return;
        }

        $previousSessionId = (int) $songRequest->getOriginal('table_session_id');

        if ($previousSessionId && $previousSessionId !== (int) $songRequest->table_session_id) {
            $this->sessionTipService->sync($previousSessionId);
        }

        $this->sessionTipService->sync($songRequest->table_session_id);
    }

    public function deleted(SongRequest $songRequest): void
    {
        $this->sessionTipService->sync($songRequest->table_session_id);
    }
}
