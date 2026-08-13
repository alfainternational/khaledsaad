<?php

namespace Tests\Unit\Support\AI;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Modules\Shared\I18n\GenerationLocale;
use App\Modules\Shared\I18n\LocaleRegistry;
use App\Support\AI\AIRequest;
use App\Support\AI\AIResponse;
use App\Support\AI\JsonSchemaValidator;
use App\Support\AI\StructuredRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الإنقاذ: مخرج فيه عنصر مصفوفة واحد معطوب لا يُسقط التقرير كله.
 * نبقي العناصر السليمة ونتجاهل المعطوب، فيبقى المخرج مفيدًا.
 */
class StructuredRunnerSalvageTest extends TestCase
{
    use RefreshDatabase;

    private const SCHEMA = [
        'type' => 'object',
        'required' => ['findings'],
        'properties' => [
            'findings' => [
                'type' => 'array',
                'minItems' => 3,
                'items' => [
                    'type' => 'object',
                    'required' => ['title'],
                    'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 5],
                    ],
                ],
            ],
        ],
    ];

    #[Test]
    public function it_keeps_valid_items_and_drops_the_broken_one_when_salvage_is_on(): void
    {
        // ثلاث نتائج سليمة وواحدة معطوبة (عنوان قصير). الحد الأدنى 3 يسقط الكل عادةً.
        $payload = [
            'findings' => [
                ['title' => 'نتيجة أولى واضحة ومكتملة'],
                ['title' => 'x'], // معطوبة: أقصر من الحد
                ['title' => 'نتيجة ثانية واضحة ومكتملة'],
                ['title' => 'نتيجة ثالثة واضحة ومكتملة'],
            ],
        ];

        $result = $this->execute($payload, salvage: true);

        $this->assertCount(3, $result['findings']);
        foreach ($result['findings'] as $finding) {
            $this->assertGreaterThanOrEqual(5, mb_strlen($finding['title']));
        }
    }

    #[Test]
    public function without_salvage_a_broken_item_still_fails_the_whole_output(): void
    {
        $this->expectExceptionMessage('استنفاد المحاولات');

        $payload = [
            'findings' => [
                ['title' => 'نتيجة واحدة سليمة فقط'],
                ['title' => 'x'],
            ],
        ];

        $this->execute($payload, salvage: false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function execute(array $payload, bool $salvage): array
    {
        $response = new AIResponse(
            content: json_encode($payload, JSON_UNESCAPED_UNICODE),
            provider: 'test',
            model: 'test-model',
            inputTokens: 10,
            outputTokens: 10,
            latencyMs: 1,
            costUsd: 0.0,
        );

        $gateway = \Mockery::mock(ArtificialIntelligenceGateway::class);
        $gateway->shouldReceive('run')->andReturn($response);

        $runner = new StructuredRunner(
            $gateway,
            new JsonSchemaValidator,
            // توجيه لغة المخرَج: لغة المصدر تُرجع نصًّا فارغًا، فلا يتغيّر الطلب هنا.
            new GenerationLocale(new LocaleRegistry),
        );

        $request = AIRequest::json(
            messages: [['role' => 'user', 'content' => 'اختبار']],
            schema: self::SCHEMA,
            salvage: $salvage,
        );

        return $runner->run($request);
    }
}
