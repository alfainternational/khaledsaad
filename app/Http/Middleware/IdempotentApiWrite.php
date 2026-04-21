<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotentApiWrite
{
    public function handle(Request $request, Closure $next, string $scope = 'default'): Response
    {
        if ($request->method() !== 'POST' && $request->method() !== 'PATCH') {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $next($request);
        }

        $user = $request->user();
        $hashKey = 'api_idem:'.$scope.':'.sha1($user->id.'|'.$idempotencyKey.'|'.$request->path().'|'.$request->getContent());

        if (Cache::has($hashKey)) {
            $payload = Cache::get($hashKey);

            return response()->json($payload['body'], $payload['status']);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            Cache::put($hashKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], now()->addDay());
        }

        return $response;
    }
}
