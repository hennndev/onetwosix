<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSongRequestRequest;
use App\Http\Resources\SongRequestResource;
use App\Models\SongRequest;
use App\Models\TableSession;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class SongRequestController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $songRequests = SongRequest::with('customerUser.user')
            ->whereIn('status', ['pending', 'played'])
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->success([
            'song_requests' => SongRequestResource::collection($songRequests),
        ]);
    }

    public function store(StoreSongRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->customerUser) {
            return $this->error('Akun customer tidak ditemukan.', 403);
        }

        $activeSession = TableSession::query()
            ->where('customer_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $activeSession) {
            return $this->error('Kamu tidak memiliki booking aktif. Request lagu hanya bisa dilakukan saat check-in.', 403);
        }

        $validated = $request->validated();
        $tip = (float) ($validated['tip'] ?? 0);

        $songRequest = SongRequest::create([
            'customer_user_id' => $user->customerUser->id,
            'table_session_id' => $activeSession->id,
            'song_title' => $validated['song_title'],
            'artist' => $validated['artist'],
            'tip' => $tip,
            'status' => 'pending',
        ]);

        $songRequest->load('customerUser.user');

        return $this->success([
            'song_request' => new SongRequestResource($songRequest),
        ], 'Song request berhasil dikirim.', 201);
    }

    public function show(SongRequest $songRequest): JsonResponse
    {
        $songRequest->load('customerUser.user');

        return $this->success([
            'song_request' => new SongRequestResource($songRequest),
        ]);
    }
}
