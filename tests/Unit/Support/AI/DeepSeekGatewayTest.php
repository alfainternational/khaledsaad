<?php

namespace Tests\Unit\Support\AI;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Exceptions\AIProviderException;
use App\Support\AI\AIRequest;
use App\Support\AI\DeepSeekGateway;
use App\Support\AI\Resilience\FallbackChainGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeepSeekGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.deepseek', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.deepseek.com',
            'model' => 'deepseek-v4-flash',
            'timeout' => 60,
            'tiers' => [
                'economy' => 'deepseek-v4-flash',
                'standard' => 'deepseek-v4-flash',
                'advanced' => 'deepseek-reasoner',
            ],
        ]);
    }

    /**
     * العقد يُحلّ إلى **سلسلة** لا إلى مزوّد واحد.
     *
     * كان يُحلّ إلى `DeepSeekGateway` مباشرة، فنفادُ اشتراكه أوقف المنصة
     * كلها ولم يكن خلفه أحد. ما يحرسه هذا الاختبار الآن هو أن كل مستدعٍ
     * يمرّ بالسلسلة — لأن مستدعيًا واحدًا يتجاوزها يعيد نقطة الانهيار.
     */
    #[Test]
    public function the_ai_contract_resolves_to_a_provider_chain(): void
    {
        $gateway = app(ArtificialIntelligenceGateway::class);

        $this->assertInstanceOf(FallbackChainGateway::class, $gateway);
        $this->assertArrayHasKey('deepseek', $gateway->health());
    }

    #[Test]
    public function it_sends_a_request_and_normalizes_the_response(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => '{"status":"ready"}'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 5],
            ]),
        ]);

        $response = app(DeepSeekGateway::class)->run(
            AIRequest::text([['role' => 'user', 'content' => 'حلل المشروع']]),
        );

        $this->assertSame('{"status":"ready"}', $response->content);
        $this->assertSame('deepseek-v4-flash', $response->model);
        $this->assertSame(17, $response->totalTokens());
        $this->assertSame('stop', $response->finishReason);
    }

    #[Test]
    public function it_requests_json_mode_when_a_schema_is_expected(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        app(DeepSeekGateway::class)->run(AIRequest::json(
            messages: [['role' => 'user', 'content' => 'أعد JSON']],
            schema: ['type' => 'object'],
            stage: 'synthesis',
        ));

        // بدون response_format لا يوجد ضمان لبنية المخرج، وكل تحقق لاحق يصبح تخمينًا.
        Http::assertSent(fn ($request) => $request['response_format']['type'] === 'json_object');
    }

    #[Test]
    public function it_sends_the_schema_itself_to_the_model(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"ok":true}']]],
                'usage' => [],
            ]),
        ]);

        app(DeepSeekGateway::class)->run(AIRequest::json(
            messages: [['role' => 'user', 'content' => 'حلل']],
            schema: [
                'type' => 'object',
                'required' => ['findings'],
                'properties' => ['findings' => ['type' => 'array']],
            ],
        ));

        // json_object يضمن JSON صالحًا لا شكلًا معينًا. بدون إرسال المخطط كان
        // النموذج يخترع أسماء مفاتيح، فيُرفض كل مخرج بمخالفة «حقل مطلوب مفقود».
        Http::assertSent(function ($request): bool {
            $payload = json_encode($request['messages'], JSON_UNESCAPED_UNICODE);

            return str_contains($payload, 'findings')
                && str_contains($payload, 'required')
                && str_contains($payload, 'لا تترجمها');
        });
    }

    #[Test]
    public function it_selects_the_model_that_matches_the_requested_tier(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{}']]],
                'usage' => [],
            ]),
        ]);

        app(DeepSeekGateway::class)->run(new AIRequest(
            messages: [['role' => 'user', 'content' => 'اختبار']],
            tier: 'advanced',
        ));

        Http::assertSent(fn ($request) => $request['model'] === 'deepseek-reasoner');
    }

    #[Test]
    public function it_calculates_cost_and_latency_for_every_call(): void
    {
        config()->set('ai.pricing.deepseek-v4-flash', ['input' => 1.0, 'output' => 2.0]);

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => '{}']]],
                'usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 500_000],
            ]),
        ]);

        $response = app(DeepSeekGateway::class)->run(
            AIRequest::text([['role' => 'user', 'content' => 'اختبار']]),
        );

        // مليون رمز دخل بدولار + نصف مليون رمز خرج بدولارين للمليون = دولاران.
        $this->assertSame(2.0, $response->costUsd);
        $this->assertGreaterThanOrEqual(0, $response->latencyMs);
        $this->assertArrayHasKey('cost_usd', $response->usageRecord());
    }

    #[Test]
    public function it_streams_chunks_and_returns_the_assembled_response(): void
    {
        $body = implode("\n", [
            'data: {"choices":[{"delta":{"content":"مرحبًا"}}]}',
            'data: {"choices":[{"delta":{"content":" بك"}}],"usage":{"prompt_tokens":3,"completion_tokens":4}}',
            'data: [DONE]',
            '',
        ]);

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response($body),
        ]);

        $chunks = [];
        $response = app(DeepSeekGateway::class)->stream(
            AIRequest::text([['role' => 'user', 'content' => 'رحّب بي']]),
            function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        );

        $this->assertSame(['مرحبًا', ' بك'], $chunks);
        $this->assertSame('مرحبًا بك', $response->content);
        $this->assertSame(7, $response->totalTokens());
        Http::assertSent(fn ($request) => $request['stream'] === true);
    }

    #[Test]
    public function it_converts_provider_errors_without_exposing_the_api_key(): void
    {
        config()->set('ai.deepseek.api_key', 'secret-test-key');

        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'error' => ['message' => 'Upstream rejected secret-test-key'],
            ], 429),
        ]);

        try {
            app(DeepSeekGateway::class)->run(
                AIRequest::text([['role' => 'user', 'content' => 'حلل المشروع']]),
            );

            $this->fail('Expected an AI provider exception.');
        } catch (AIProviderException $exception) {
            $this->assertSame(429, $exception->statusCode);
            // اسم المزوّد الفعلي لا اسمًا معروضًا ثابتًا: السلسلة تحتاج أن
            // تعرف مَن سقط، وسجلٌّ يقول «DeepSeek» عن عطلٍ في مزوّد آخر يضلّل.
            $this->assertStringContainsString('deepseek', $exception->getMessage());
            $this->assertStringNotContainsString('secret-test-key', $exception->getMessage());
        }
    }
}
