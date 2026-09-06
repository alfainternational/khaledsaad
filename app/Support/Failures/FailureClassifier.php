<?php

declare(strict_types=1);

namespace App\Support\Failures;

use App\Exceptions\AIInvalidOutputException;
use App\Exceptions\AIProviderException;
use App\Exceptions\BillingLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * يحوّل الاستثناء إلى عطل يفهمه المستخدم — نقطة العبور الوحيدة.
 *
 * قبل هذا الصنف كانت `failure_reason` تحمل `$exception->getMessage()` وتُطبع
 * كما هي في الشاشة. أي رسالة من مكتبة أو مزوّد كانت تصل للمستخدم حرفيًا،
 * فقرأ رسالة حصة المزوّد على أنها رصيده هو. المخرج الوحيد أن يمرّ كل عطل
 * من هنا، وأن تبقى الرسالة الأصلية في السجلّ وحده.
 */
final class FailureClassifier
{
    /**
     * الأكواد التي تعني «نفدت قدرتنا نحن» لا «أخطأ المستخدم».
     * ٤٠٢ فوترة المزوّد، ٤٢٩ حدّ معدله، و‎5xx‎ انقطاعه.
     */
    private const PROVIDER_EXHAUSTED = [402, 429];

    public function classify(Throwable $exception): RunFailure
    {
        return match (true) {
            $exception instanceof BillingLimitException => $this->fromBillingLimit($exception),
            $exception instanceof AIProviderException => $this->fromProvider($exception),
            $exception instanceof AIInvalidOutputException => $this->invalidOutput(),
            $exception instanceof ConnectionException => $this->connection(),
            $exception instanceof ValidationException => $this->fromValidation($exception),
            default => $this->unknown(),
        };
    }

    /**
     * حدّ يخصّ المستخدم فعلًا: هنا وحدها يجوز ذكر رصيده أو خطته.
     */
    private function fromBillingLimit(BillingLimitException $exception): RunFailure
    {
        $isQuota = $exception->kind === BillingLimitException::KIND_QUOTA;

        return new RunFailure(
            kind: FailureKind::Theirs,
            code: $isQuota ? 'plan_quota_reached' : 'insufficient_credits',
            title: $isQuota
                ? __('استهلكت حصة خطتك لهذا الشهر')
                : __('رصيدك لا يكفي لتشغيل هذه الأداة'),
            message: $exception->getMessage().' '.__('إجاباتك محفوظة، ويكمل التشغيل من حيث توقّف.'),
            userAction: new RunFailureAction(
                label: $isQuota ? __('ارفع خطتك') : __('اشحن رصيدك'),
                route: 'app.billing',
            ),
        );
    }

    /**
     * عطل المزوّد: عطلنا نحن. لا رصيد يُذكر، ولا إجراء يُطلب.
     *
     * المهلة المقترحة تختلف بالسبب: حدّ المعدل يزول خلال دقائق، ونفاد
     * الاشتراك يحتاج تدخلًا منّا — فلا نَعِد المستخدم بربع ساعة كاذبة.
     */
    private function fromProvider(AIProviderException $exception): RunFailure
    {
        $status = $exception->statusCode;
        $exhausted = $status !== null && in_array($status, self::PROVIDER_EXHAUSTED, true);

        return new RunFailure(
            kind: FailureKind::Ours,
            code: $exhausted ? 'provider_unavailable' : 'provider_error',
            title: __('تعذّر تشغيل التحليل — والسبب لدينا لا لديك'),
            message: __('إجاباتك محفوظة بالكامل، ولم يُخصم من رصيدك شيء. سنشغّل التحليل تلقائيًا فور عودة الخدمة ونُشعرك.'),
            retryAfter: $status === 429 ? 900 : 3600,
        );
    }

    /**
     * مخرج لا يطابق الشكل المتوقع: خللٌ في طرفنا حتى لو أتى من المزوّد،
     * لأن المستخدم لا يملك تغيير ما يعيده النموذج.
     */
    private function invalidOutput(): RunFailure
    {
        return new RunFailure(
            kind: FailureKind::Ours,
            code: 'invalid_output',
            title: __('وصل التحليل ناقصًا — والخلل لدينا'),
            message: __('إجاباتك محفوظة ولم يُخصم من رصيدك شيء. نعيد التشغيل تلقائيًا، ونُشعرك عند اكتمال تقريرك.'),
            retryAfter: 600,
        );
    }

    private function connection(): RunFailure
    {
        return new RunFailure(
            kind: FailureKind::Ours,
            code: 'connection_failed',
            title: __('انقطع الاتصال أثناء التحليل'),
            message: __('إجاباتك محفوظة ولم يُخصم من رصيدك شيء. نعيد المحاولة تلقائيًا خلال دقائق.'),
            retryAfter: 300,
        );
    }

    private function fromValidation(ValidationException $exception): RunFailure
    {
        return new RunFailure(
            kind: FailureKind::Input,
            code: 'invalid_input',
            title: __('نحتاج إكمال بعض الإجابات'),
            message: collect($exception->errors())->flatten()->implode(' '),
            // لا مسار: الحقل الناقص يُصحَّح في مكانه، ونقل المستخدم لصفحة
            // أخرى ليصحّح حقلًا أمامه إضاعةٌ لسياقه.
            userAction: new RunFailureAction(label: __('أكمل الإجابات')),
        );
    }

    /**
     * المجهول يُعامَل كعطلنا: افتراض براءة المستخدم أرخص من اتّهامه خطأً.
     */
    private function unknown(): RunFailure
    {
        return new RunFailure(
            kind: FailureKind::Ours,
            code: 'run_failed',
            title: __('توقّف التحليل قبل أن يكتمل'),
            message: __('إجاباتك محفوظة بالكامل، ولم يُخصم من رصيدك شيء. نحن نتابع السبب ونعيد التشغيل — ويصلك إشعار عند جاهزية تقريرك.'),
            retryAfter: 1800,
        );
    }
}
