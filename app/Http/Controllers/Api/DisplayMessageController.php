<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDisplayMessageRequest;
use App\Http\Resources\DisplayMessageResource;
use App\Models\DisplayMessageRequest;
use App\Models\TableSession;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DisplayMessageController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $messages = DisplayMessageRequest::with('customer')
            ->whereIn('status', ['pending', 'displayed'])
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->success([
            'display_messages' => DisplayMessageResource::collection($messages),
        ]);
    }

    public function store(StoreDisplayMessageRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $activeSession = TableSession::query()
            ->where('customer_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $activeSession) {
            return $this->error('Kamu tidak memiliki booking aktif. Display message hanya bisa dikirim saat check-in.', 403);
        }

        $validated = $request->validated();
        $tip = (int) ($validated['tip'] ?? 0);

        $displayMessage = DisplayMessageRequest::create([
            'customer_id' => $user->id,
            'table_session_id' => $activeSession->id,
            'message' => $validated['message'],
            'tip' => $tip,
            'status' => 'pending',
        ]);

        $displayMessage->load('customer');

        return $this->success([
            'display_message' => new DisplayMessageResource($displayMessage),
        ], 'Display message berhasil dikirim.', 201);
    }

    public function show(DisplayMessageRequest $displayMessage): JsonResponse
    {
        $displayMessage->load('customer');

        return $this->success([
            'display_message' => new DisplayMessageResource($displayMessage),
        ]);
    }
}
