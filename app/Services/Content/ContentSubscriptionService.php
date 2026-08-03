<?php

namespace App\Services\Content;

use App\Models\ContentSubscriber;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ContentSubscriptionService
{
    /**
     * @return array{subscriber: ContentSubscriber, token: string}
     */
    public function subscribe(string $email, bool $consent): array
    {
        if (! $consent) {
            throw new InvalidArgumentException('???????? ??? ??? ?????? ??????.');
        }

        $normalized = Str::lower(trim($email));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('?????? ?????????? ??? ????.');
        }

        $token = Str::random(64);

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
