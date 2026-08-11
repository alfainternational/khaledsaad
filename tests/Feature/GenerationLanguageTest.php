<?php

namespace Tests\Feature;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Modules\Shared\I18n\GenerationLocale;
use App\Support\AI\AIRequest;
use App\Support\AI\AIResponse;
use App\Support\AI\StructuredRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * لغة المخرَج المولَّد.
 *
 * سبب وجود هذا الاختبار: الواجهة كانت مترجمة والمنتج لا. القاعدة رقم ٢ في
 * `PipelineSchemas::systemPreamble()` تقول نصًّا «اكتب بلهجة بيضاء عربية»،
 * فكان من يختار الفرنسية يرى الأزرار بلغته و**التقرير** بالعربية. وهو عطل
 * لا يظهر في أي اختبار وحدة، لأن المخرَج يأتي من نموذج لا من كودنا.
 *
 * ما يُحرَس هنا أربعة: أن التوجيه يصل الطلب فعلًا، وأن العربية لا يتغيّر
 * سلوكها بحرف، وأن المترجم والقياس معفيان، وأن التوجيه يبقى بعد إعادة
 * المحاولة — وهو أخطرها لأن سقوطه هناك يُنتج عطلًا متقطّعًا.
 */
class GenerationLanguageTest extends TestCase
{
    // `StructuredRunner` يسجّل تكلفة كل استدعاء في `ai_usage_records`.
    use RefreshDatabase;

    /** آخر طلب وصل المزوّد. */
    private ?AIRequest $captured = null;

    protected function setUp(): void
    {
        parent::setUp();

        $gateway = new class($this) implements ArtificialIntelligenceGateway
        {
            public function __construct(private readonly GenerationLanguageTest $test) {}

            public function run(AIRequest $request): AIResponse
            {
                $this->test->capture($request);

                return new AIResponse(
                    content: json_encode(['ok' => true], JSON_UNESCAPED_UNICODE),
                    provider: 'test',
                    model: 'test-model',
                    inputTokens: 1,
                    outputTokens: 1,
                    latencyMs: 1,
                    costUsd: 0.0,
                    stage: $request->stage,
                );
            }

            public function stream(AIRequest $request, \Closure $onChunk): AIResponse
            {
                return $this->run($request);
            }

            public function provider(): string
            {
                return 'test';
            }

            public function modelForTier(string $tier): string
            {
                return 'test-model';
            }
        };

        $this->app->instance(ArtificialIntelligenceGateway::class, $gateway);
    }

    public function capture(AIRequest $request): void
    {
        $this->captured = $request;
    }

    private function runOnce(AIRequest $request): void
    {
        $this->app->make(StructuredRunner::class)->run($request);
    }

    private function systemText(): string
    {
        $text = '';

        foreach ($this->captured?->messages ?? [] as $message) {
            if (($message['role'] ?? '') === 'system') {
                $text .= $message['content']."\n";
            }
        }

        return $text;
    }

    private function request(bool $localeNeutral = false, ?string $outputLocale = null): AIRequest
    {
        return AIRequest::json(
            messages: [
                ['role' => 'system', 'content' => 'اكتب بلهجة بيضاء عربية.'],
                ['role' => 'user', 'content' => 'بيانات'],
            ],
            schema: ['type' => 'object'],
            localeNeutral: $localeNeutral,
            outputLocale: $outputLocale,
        );
    }

    public function test_arabic_generation_is_left_exactly_as_it_was(): void
    {
        $this->app->setLocale('ar');
        $this->runOnce($this->request());

        $this->assertSame(
            "اكتب بلهجة بيضاء عربية.\n",
            $this->systemText(),
            'لغة المصدر يجب ألّا تُحقن بأي توجيه — المنتج العربي لا يتغيّر سلوكه.',
        );
    }

    public function test_a_french_reader_gets_a_french_output_directive(): void
    {
        $this->app->setLocale('fr');
        $this->runOnce($this->request());

        $this->assertStringContainsString('French', $this->systemText());
        $this->assertStringContainsString('OUTPUT LANGUAGE', $this->systemText());
    }

    /**
     * التوجيه بعد التعليمة العربية لا قبلها.
     *
     * النماذج تُرجّح آخر تعليمة عند التعارض، والبرومبت يحمل «اكتب بالعربية»
     * في وسطه. توجيهٌ يسبقه يخسر المنافسة معه بصمت — ويخرج التقرير عربيًّا
     * رغم أن كل شيء في الكود يبدو صحيحًا.
     */
    public function test_the_directive_comes_after_the_arabic_instruction_it_overrides(): void
    {
        $this->app->setLocale('en');
        $this->runOnce($this->request());

        $text = $this->systemText();

        $this->assertLessThan(
            strpos($text, 'OUTPUT LANGUAGE'),
            strpos($text, 'اكتب بلهجة بيضاء عربية.'),
        );
    }

    public function test_the_translator_and_measurement_are_never_told_a_language(): void
    {
        $this->app->setLocale('fr');
        $this->runOnce($this->request(localeNeutral: true));

        $this->assertStringNotContainsString('OUTPUT LANGUAGE', $this->systemText());
    }

    /**
     * لغة صريحة تتقدّم على لغة الطلب — وهو ما يجعل مهمة في طابور تُنتج
     * بلغة صاحبها لا بلغة العملية التي تشغّلها.
     */
    public function test_an_explicit_locale_wins_over_the_request_locale(): void
    {
        $this->app->setLocale('ar');
        $this->runOnce($this->request(outputLocale: 'en'));

        $this->assertStringContainsString('English', $this->systemText());
    }

    public function test_a_request_without_a_system_message_still_gets_the_directive(): void
    {
        $this->app->setLocale('en');

        $this->runOnce(AIRequest::json(
            messages: [['role' => 'user', 'content' => 'بيانات']],
            schema: ['type' => 'object'],
        ));

        $this->assertSame('system', $this->captured->messages[0]['role']);
        $this->assertStringContainsString('OUTPUT LANGUAGE', $this->systemText());
    }

    public function test_the_source_locale_produces_no_directive_at_all(): void
    {
        $directive = $this->app->make(GenerationLocale::class);

        $this->assertSame('', $directive->directive('ar'));
        $this->assertNotSame('', $directive->directive('en'));
        $this->assertSame('', $directive->directive('zz'), 'لغة غير مفعّلة لا تُنتج توجيهًا.');
    }
}
