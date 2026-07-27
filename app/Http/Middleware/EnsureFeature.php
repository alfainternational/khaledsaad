<?php

namespace App\Http\Middleware;

use App\Models\Feature;
use App\Services\Billing\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة ميزة على مستوى المسار: feature:reports.pdf
 *
 * الممنوع لا يُخفى بصمت — يُعاد المستخدم إلى الفوترة برسالة تقول أي عنصر
 * ينقصه، لأن الهدف ترقية واعية لا حائط مسدود.
 */
class EnsureFeature
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        $user = $request->user();

        if ($user === null || $this->entitlements->allows($user->primaryWorkspace(), $key)) {
            return $next($request);
        }

        $name = Feature::where('key', $key)->value('name') ?? $key;
        $message = "«{$name}» غير متاحة في خطتك الحالية.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'feature' => $key], 403);
        }

        return redirect()->route('app.billing')->withErrors(['feature' => $message]);
    }
}
