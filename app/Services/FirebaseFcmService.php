<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirebaseFcmService
{
    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        if (blank($token)) {
            return ['sent' => false, 'message' => 'Token Firebase kosong.'];
        }

        try {
            $serviceAccount = $this->serviceAccount();
            $projectId = (string) $serviceAccount['project_id'];

            $response = Http::withToken($this->accessToken($serviceAccount))
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => collect($data)->map(fn (mixed $value): string => (string) $value)->all(),
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Firebase FCM notification failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }

            return ['sent' => $response->successful(), 'status' => $response->status(), 'response' => $response->json()];
        } catch (\Throwable $e) {
            Log::warning('Firebase FCM notification error', ['message' => $e->getMessage()]);

            return ['sent' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serviceAccount(): array
    {
        $path = (string) config('services.firebase.service_account_file');

        if (! file_exists($path)) {
            throw new RuntimeException('File service account Firebase tidak ditemukan.');
        }

        $serviceAccount = json_decode((string) file_get_contents($path), true);

        if (! is_array($serviceAccount) || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key']) || empty($serviceAccount['token_uri']) || empty($serviceAccount['project_id'])) {
            throw new RuntimeException('File service account Firebase tidak valid.');
        }

        return $serviceAccount;
    }

    /**
     * @param  array<string, mixed>  $serviceAccount
     */
    protected function accessToken(array $serviceAccount): string
    {
        $issuedAt = time();
        $unsignedJwt = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR))
            .'.'.$this->base64UrlEncode(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $serviceAccount['token_uri'],
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], JSON_THROW_ON_ERROR));

        openssl_sign($unsignedJwt, $signature, (string) $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = $unsignedJwt.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()->post((string) $serviceAccount['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengambil access token Firebase.');
        }

        return (string) $response->json('access_token');
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
