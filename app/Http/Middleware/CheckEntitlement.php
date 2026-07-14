<?php

namespace App\Http\Middleware;

use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Api\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a boolean plan/workspace entitlement.
 *
 * Usage:  ->middleware('entitlement:outputs.can_export')
 *
 * Resolves the active workspace bound by ResolveWorkspaceContext, then denies with 403
 * when the entitlement is falsy. This is the enforcement layer that makes the business
 * model real: "subscription unlocks export/copy/print". Without it, paid features leak.
 */
class CheckEntitlement
{
    public function __construct(private readonly EntitlementResolver $entitlements)
    {
    }

    public function handle(Request $request, Closure $next, string $key): Response
    {
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        abort_unless($workspace instanceof Workspace, 403, 'لا توجد مساحة عمل مرتبطة بهذا الإجراء.');

        if (! $this->entitlements->boolean($key, $workspace)) {
            // رمز دلالي مميّز (ENTITLEMENT_REQUIRED) كي يفرّق العميل بين "ممنوع"
            // و"يتطلّب ترقية الباقة" ويعرض دعوة الترقية بدل رسالة خطأ عامة.
            throw ApiException::entitlementRequired(
                'هذه الميزة متاحة ضمن باقة أعلى. رقِّ اشتراكك للوصول إليها.'
            );
        }

        return $next($request);
    }
}
