<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureApiProjectInWorkspace;
use App\Http\Middleware\EnsureApiSuperAdmin;
use App\Http\Middleware\EnsureApiWorkspaceMember;
use App\Http\Middleware\IdempotentApiWrite;
use App\Http\Middleware\ResolveWorkspaceContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

require_once __DIR__.'/../app/Support/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'api.workspace' => EnsureApiWorkspaceMember::class,
            'api.project' => EnsureApiProjectInWorkspace::class,
            'api.super_admin' => EnsureApiSuperAdmin::class,
            'idempotency' => IdempotentApiWrite::class,
        ]);

        $middleware->web(append: [
            ResolveWorkspaceContext::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'paypal/webhook',
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
