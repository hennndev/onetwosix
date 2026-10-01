<?php

namespace App\Observers;

use App\Models\DisplayMessageRequest;
use App\Services\SessionTipService;

class DisplayMessageRequestObserver
{
    public function __construct(protected SessionTipService $sessionTipService) {}

    public function created(DisplayMessageRequest $displayMessageRequest): void
    {
        $this->sessionTipService->sync($displayMessageRequest->table_session_id);
    }

    public function updated(DisplayMessageRequest $displayMessageRequest): void
    {
        if (! $displayMessageRequest->wasChanged(['status', 'tip', 'table_session_id'])) {
            return;
        }

        $previousSessionId = (int) $displayMessageRequest->getOriginal('table_session_id');

        if ($previousSessionId && $previousSessionId !== (int) $displayMessageRequest->table_session_id) {
            $this->sessionTipService->sync($previousSessionId);
        }

        $this->sessionTipService->sync($displayMessageRequest->table_session_id);
    }

    public function deleted(DisplayMessageRequest $displayMessageRequest): void
    {
        $this->sessionTipService->sync($displayMessageRequest->table_session_id);
    }
}
