<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

/**
 * نسخة آمنة من AuthenticateSession (بند ٢٣ — «خروج البقية»):
 * الحارس الأصلي يفترض SessionGuard ويستدعي viaRemember، فينفجر على مسارات
 * الويب حين تكون المصادقة عبر Sanctum (اختبارات التكافؤ وتطبيق الموبايل).
 * هنا يعمل على جلسات المتصفح الحقيقية فقط ويعبر ما عداها بصمت.
 */
class AuthenticateBrowserSession extends AuthenticateSession
{
    public function handle($request, Closure $next)
    {
        if (! $request instanceof Request
            || ! $request->hasSession()
            || ! $this->auth->guard() instanceof SessionGuard) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
