<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * بوابة إشعارات push عبر FCM HTTP v1.
 *
 * آمنة بالتصميم: إن لم تُضبط بيانات اعتماد FCM (خدمة Google JSON) تعمل كـ no-op
 * دون أي خطأ — فالمنصة تعمل كاملة بدون إشعارات حتى تُضاف المفاتيح.
 *
 * الإعداد المطلوب في config/services.php:
 *   'fcm' => [
 *       'project_id' => env('FCM_PROJECT_ID'),
 *       'credentials' => env('FCM_CREDENTIALS_PATH'), // مسار ملف service-account.json
 *   ]
 */
class PushGateway
{
    public function isConfigured(): bool
    {
        $projectId = (string) config('services.fcm.project_id', '');
        $credentials = (string) config('services.fcm.credentials', '');

        return $projectId !== '' && $credentials !== '' && is_file($credentials);
    }

    /**
     * إرسال إشعار لكل أجهزة مستخدم. data تُمرَّر للتطبيق للتوجيه العميق.
     *
     * @param  array<string, string>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->pluck('token');

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    private function sendToToken(string $token, string $title, string $body, array $data): void
    {
        try {
            $accessToken = $this->accessToken();
            if ($accessToken === null) {
                return;
            }

            $projectId = (string) config('services.fcm.project_id');

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ]);

            // توكن غير صالح → نظّفه.
            if ($response->status() === 404 || $response->status() === 400) {
                DeviceToken::query()->where('token', $token)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('PushGateway send failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * OAuth2 access token من service account (JWT bearer grant) مع كاش قصير.
     */
    private function accessToken(): ?string
    {
        return cache()->remember('fcm.access_token', now()->addMinutes(50), function (): ?string {
            try {
                $credentials = json_decode(
                    (string) file_get_contents((string) config('services.fcm.credentials')),
                    true,
                );
                if (! is_array($credentials)) {
                    return null;
                }

                $now = time();
                $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $claims = $this->b64(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));

                $signature = '';
                openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], 'sha256WithRSAEncryption');
                $jwt = "{$header}.{$claims}.".$this->b64($signature);

                $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::warning('PushGateway token failed', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
