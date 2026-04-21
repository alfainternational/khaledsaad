<?php

namespace App\Domain\Integration\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class CloudIntegrationException extends RuntimeException
{
    public static function configurationMissing(): self
    {
        return new self(
            'تكامل السحابة غير مفعّل أو غير مُعدّ. اضبط CLOUD_INTEGRATION_ENABLED و CLOUD_BASE_URL.'
        );
    }

    public static function gateDenied(string $reason): self
    {
        return new self(match ($reason) {
            'feature_flag' => 'ميزة تكامل السحابة غير مفعّلة لهذا السياق.',
            'entitlement' => 'باقة الاشتراك الحالية لا تتضمن تكامل السحابة.',
            default => 'الوصول إلى تكامل السحابة غير مسموح.',
        });
    }

    public static function rateLimited(): self
    {
        return new self('تم تجاوز حد طلبات تكامل السحابة لهذا الـ workspace. حاول لاحقاً.');
    }

    public static function fromFailedResponse(Response $response, bool $exposeDetail): self
    {
        $status = $response->status();
        $body = $response->body();
        $snippet = $exposeDetail
            ? (mb_strlen($body) > 2000 ? mb_substr($body, 0, 2000).'…' : $body)
            : '';

        $message = $exposeDetail && $snippet !== ''
            ? 'فشل طلب تكامل السحابة: HTTP '.$status.' — '.$snippet
            : 'فشل طلب تكامل السحابة: HTTP '.$status.'.';

        return new self($message);
    }

    public static function fromConnection(Throwable $previous): self
    {
        return new self(
            'تعذر الاتصال بخدمة تكامل السحابة. تحقق من الشبكة أو العنوان.',
            0,
            $previous
        );
    }
}
