<?php

namespace App\Http\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * يحوّل أي استثناء في مسار API إلى العقد الموحّد:
 * { "message": "...", "code": "SNAKE_CODE", "errors"?: { field: [..] } }
 */
class ApiExceptionRenderer
{
    /**
     * هل يجب أن يُعالج هذا الطلب كـ API (JSON)؟
     */
    public static function handles(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::handles($request)) {
            return null;
        }

        // استثناء API الموحّد (يحمل رمزه بنفسه).
        if ($e instanceof ApiException) {
            return self::payload($e->getMessage(), $e->errorCode, $e->status, $e->errors);
        }

        if ($e instanceof ValidationException) {
            return self::payload(
                $e->getMessage(),
                'VALIDATION_ERROR',
                422,
                $e->errors(),
            );
        }

        if ($e instanceof AuthenticationException) {
            return self::payload('يلزم تسجيل الدخول.', 'UNAUTHENTICATED', 401);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return self::payload(
                $e->getMessage() ?: 'ليست لديك صلاحية لهذا الإجراء.',
                'FORBIDDEN',
                403,
            );
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            $body = self::payload('العنصر المطلوب غير موجود.', 'NOT_FOUND', 404);
            if (config('app.debug')) {
                $data = $body->getData(true);
                $data['debug_exception'] = $e::class.($e->getMessage() !== '' ? ': '.$e->getMessage() : '');
                $body->setData($data);
            }

            return $body;
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return self::payload('عدد الطلبات كبير جداً، حاول لاحقاً.', 'RATE_LIMITED', 429);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return self::payload(
                $e->getMessage() ?: 'حدث خطأ.',
                self::codeForStatus($status),
                $status,
            );
        }

        // خطأ غير متوقع — لا نكشف التفاصيل في الإنتاج.
        $message = config('app.debug') ? $e->getMessage() : 'حدث خطأ غير متوقع في الخادم.';

        return self::payload($message, 'SERVER_ERROR', 500);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private static function payload(string $message, string $code, int $status, array $errors = []): JsonResponse
    {
        $body = [
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            402 => 'PAYMENT_REQUIRED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            default => $status >= 500 ? 'SERVER_ERROR' : 'ERROR',
        };
    }
}
