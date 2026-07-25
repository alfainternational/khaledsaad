<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * تحصر لوحة الإدارة بمن يملك صلاحية admin.
 *
 * تخفي لوحة الويب نفسها برمز 404، بينما تعيد الواجهة البرمجية 403
 * حتى يستطيع التطبيق التعامل مع الصلاحية بوضوح.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin() !== true) {
            if ($request->expectsJson()) {
                abort(403);
            }

            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
