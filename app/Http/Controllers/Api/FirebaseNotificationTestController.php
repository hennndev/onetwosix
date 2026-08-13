<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseFcmService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FirebaseNotificationTestController extends Controller
{
    use ApiResponse;

    public function __construct(protected FirebaseFcmService $firebaseFcmService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $token = (string) ($user->token_firebase ?? '');

        if (blank($token)) {
            return $this->error('Token Firebase user belum tersedia.', 422);
        }

        $result = $this->firebaseFcmService->sendToToken(
            $token,
            $validated['title'] ?? 'Test Notifikasi',
            $validated['body'] ?? 'Firebase FCM berhasil dikirim.',
            [
                'type' => 'test_notification',
                'user_id' => (string) $user->id,
            ],
        );

        if (! ($result['sent'] ?? false)) {
            return $this->error('Notifikasi gagal dikirim.', 422, $result);
        }

        return $this->success($result, 'Notifikasi berhasil dikirim.');
    }
}
