<?php

namespace App\Events;

use App\Models\AuthCodeRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuthCodeRequestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AuthCodeRequest $authCodeRequest
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('auth-requests.'.$this->authCodeRequest->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->authCodeRequest->id,
            'status' => $this->authCodeRequest->status,
            'code' => $this->authCodeRequest->code,
            'manager_note' => $this->authCodeRequest->manager_note,
        ];
    }
}
