<?php

use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureSupportedAppVersion;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'feature' => EnsureFeature::class,
        ]);

        /*
         * حارس عقد api/v1: يعمل على كل مسارات الواجهة لا على بعضها، لأن
         * تغيير العقد قد يمسّ أي منها. خامل ما دام min_supported_build صفرًا.
         */
        $middleware->api(append: [
            EnsureSupportedAppVersion::class,
        ]);

        // إشعارات البوابات تصل من خوادمها بلا جلسة ولا رمز CSRF؛
        // التحقق من صحتها يتم بتوقيع المزوّد داخل المتحكّم.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
