<?php

namespace Tests\Feature;

use App\Exceptions\AIProviderException;
use App\Exceptions\BillingLimitException;
use App\Support\Failures\FailureClassifier;
use App\Support\Failures\FailureKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * عقد `api/v1` — ما يرثه الويب والتطبيق معًا.
 *
 * الفكرة الحاكمة: ثابتُ INV-8 يُفرض **على مستوى العقد** لا على مستوى كل
 * عميل. حقل `kind` هو ما يجعل التطبيق يرث القاعدة تلقائيًّا بدل أن
 * يخترع لها صياغةً من عنده — وهكذا وُلد «رصيدك غير كافٍ» فوق عطلٍ منّا.
 */
class ApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * كل خطأ من نوع `ours` يحمل `user_action = null`.
     */
    #[Test]
    public function every_error_of_kind_ours_carries_no_user_action(): void
    {
        $exceptions = [
            new AIProviderException('deepseek', 402),
            new AIProviderException('deepseek', 429),
            new AIProviderException('deepseek', 500),
            new \RuntimeException('انهيار غير متوقع'),
        ];

        foreach ($exceptions as $exception) {
            $payload = (new FailureClassifier)->classify($exception)->toArray();

            $this->assertSame(FailureKind::Ours->value, $payload['kind']);
            $this->assertNull($payload['user_action'], 'عطلٌ لنا يحمل إجراءً مطلوبًا من المستخدم.');
        }
    }

    /**
     * وكل خطأ من نوع `theirs` يحمل إجراءً واحدًا واضحًا — لا صفرًا ولا ثلاثة.
     */
    #[Test]
    public function every_error_of_kind_theirs_carries_exactly_one_action(): void
    {
        foreach ([BillingLimitException::credits(5, 0), BillingLimitException::quota(10)] as $exception) {
            $payload = (new FailureClassifier)->classify($exception)->toArray();

            $this->assertSame(FailureKind::Theirs->value, $payload['kind']);
            $this->assertIsArray($payload['user_action']);
            $this->assertArrayHasKey('label', $payload['user_action']);
        }
    }

    /**
     * شكل الخطأ ثابت: كسرُ أحد حقوله يكسر كل عميل يقرأه.
     */
    #[Test]
    public function the_error_envelope_keeps_its_shape(): void
    {
        $payload = (new FailureClassifier)->classify(new AIProviderException('deepseek', 429))->toArray();

        foreach (['kind', 'code', 'title', 'message', 'user_action', 'retry_after'] as $field) {
            $this->assertArrayHasKey($field, $payload, "حقل مفقود من ظرف الخطأ: {$field}");
        }
    }

    /**
     * توكنز التصميم تصل بشكل يعرفه العميل، ومعها إصدارها.
     */
    #[Test]
    public function design_tokens_are_served_with_their_version(): void
    {
        $response = $this->getJson(route('api.v1.public.design-tokens'))->assertOk();

        $response->assertJsonStructure([
            'data' => ['version', 'breakpoints', 'space', 'color' => ['light', 'dark']],
            'meta' => ['version', 'server_time'],
        ]);

        // النقاط الأربع لا أكثر — العميل يبني عليها لا على أرقام مكتوبة فيه.
        $this->assertSame(
            ['sm', 'md', 'lg', 'xl'],
            array_values(array_filter(
                array_keys($response->json('data.breakpoints')),
                fn ($k) => ! str_starts_with($k, '$'),
            )),
        );
    }
}
