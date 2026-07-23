<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * تحصر لوحة الإدارة بمن يملك صلاحية admin.
 *
 * 404 لا 403: لا نؤكد وجود لوحة إدارة أصلًا لمن لا يملكها.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin() !== true) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
