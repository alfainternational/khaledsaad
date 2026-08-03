<?php

namespace App\Services\Content;

use App\Models\Content;
use App\Models\ContentSubscriber;
use Illuminate\Http\Request;

class ContentAccessService
{
    public const SESSION_KEY = 'content_access_token';

    public function canView(Content $content, ?string $token = null): bool
    {
        if (! $content->isSubscriberOnly()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        return ContentSubscriber::query()
            ->where('access_token_hash', hash('sha256', $token))
            ->where('status', ContentSubscriber::STATUS_ACTIVE)
            ->exists();
    }

    public function tokenFrom(Request $request): ?string
    {
        $token = $request->header('X-Content-Token')
            ?: $request->session()->get(self::SESSION_KEY);

        return is_string($token) && $token !== '' ? $token : null;
    }
}
