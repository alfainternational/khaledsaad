<?php

namespace App\Services\Content;

use App\Models\ContentSubscriber;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ContentSubscriptionService
{
    /**
     * @return array{subscriber: ContentSubscriber, token: string}
     */
    public function subscribe(string $email, bool $consent): array
    {
        if (! $consent) {
            throw new InvalidArgumentException('الموافقة على حفظ البريد مطلوبة.');
        }

        $normalized = Str::lower(trim($email));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('البريد الإلكتروني غير صالح.');
        }

        $token = Str::random(64);

        $existing = ContentSubscriber::query()->where('email', $normalized)->first();

        if ($existing?->status === ContentSubscriber::STATUS_DISABLED) {
            throw ValidationException::withMessages([
                'email' => 'هذا البريد موقوف من إدارة المحتوى.',
            ]);
        }

        $subscriber = ContentSubscriber::query()->updateOrCreate(
            ['email' => $normalized],
            [
                'status' => ContentSubscriber::STATUS_ACTIVE,
                'access_token_hash' => hash('sha256', $token),
                'consented_at' => now(),
                'subscribed_at' => now(),
            ],
        );

        return ['subscriber' => $subscriber, 'token' => $token];
    }
}
