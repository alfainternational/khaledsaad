<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\HybridInsightService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HybridInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);

        config()->set('ai.deepseek', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.deepseek.com',
            'model' => 'deepseek-v4-flash',
            'timeout' => 10,
            'tiers' => ['economy' => 'deepseek-v4-flash'],
        ]);
    }

    #[Test]
    public function deterministic_preview_uses_unsaved_answers_without_changing_the_run(): void
    {
        [$user, $run] = $this->draftRun();
        $draft = $this->remainingAnswers();

        $preview = app(HybridInsightService::class)->preview($run, $draft);

        $this->assertSame(100, $preview['summary']['completeness_percent']);
        $this->assertGreaterThanOrEqual(70, $preview['summary']['agency_readiness_percent']);
        $this->assertLessThanOrEqual(2, count($preview['signals']));
        $this->assertTrue(collect($preview['signals'])->contains(
            fn (array $signal) => str_contains($signal['description'], '50')
        ));
        $this->assertTrue(collect($preview['signals'])->contains(
            fn (array $signal) => $signal['type'] === 'conflict'
        ));
        $this->assertSame('not_requested', $preview['preliminary']['status']);

        $this->assertSame(4, $run->fresh()->answers()->count());
        $this->assertSame($user->id, $run->user_id);
    }

    #[Test]
    public function compact_ai_interpretation_is_preliminary_and_recorded(): void
    {
        Http::fake([
            'https://api.deepseek.com/chat/completions' => Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => json_encode([
                    'meaning' => 'الميزانية موجودة لكن القياس لا يثبت مصدر النتيجة.',
                    'risk_or_opportunity' => 'فرصة لتحويل الإنفاق إلى قرار قابل للقياس.',
                    'recommendation' => 'عرّف حدث تحويل واحدًا قبل زيادة الميزانية.',
                    'deepen_question' => 'ما الحدث الذي تعتبره تحويلًا ناجحًا؟',
                ], JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 35],
            ]),
        ]);

        [, $run] = $this->draftRun();

        $preview = app(HybridInsightService::class)->preview(
            $run,
            $this->remainingAnswers(),
            includeAi: true,
            step: 4,
        );

        $this->assertSame('ready', $preview['preliminary']['status']);
        $this->assertSame('مؤشر أولي', $preview['preliminary']['label']);
        $this->assertStringContainsString('حدث تحويل', $preview['preliminary']['recommendation']);
        $this->assertDatabaseHas('ai_usage_records', [
            'tool_run_id' => $run->id,
            'stage' => 'micro-insight',
        ]);
    }

    #[Test]
    public function web_and_api_endpoints_share_the_same_preview_contract(): void
    {
        [$user, $run] = $this->draftRun();
        $payload = ['answers' => $this->remainingAnswers(), 'step' => 4];

        $this->actingAs($user)
            ->postJson(route('app.runs.insights', $run), $payload)
            ->assertOk()
            ->assertJsonPath('data.summary.completeness_percent', 100)
            ->assertJsonPath('data.preliminary.status', 'not_requested');

        Sanctum::actingAs($user);
        $this->postJson(route('api.v1.runs.insights', $run), $payload)
            ->assertOk()
            ->assertJsonPath('data.summary.completeness_percent', 100)
            ->assertJsonStructure(['data' => ['summary', 'signals', 'preliminary']]);
    }

    /**
     * @return array{0: User, 1: ToolRun}
     */
    private function draftRun(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع المؤشرات']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        app(ToolRunService::class)->saveStep($run, 1, [
            'business_model' => 'services',
            'description' => str_repeat('خدمة تسويق عملية للشركات الصغيرة ', 3),
            'geography' => 'الرياض',
            'monthly_budget' => 5000,
        ]);

        return [$user, $run->fresh()];
    }

    /**
     * @return array<string, mixed>
     */
    private function remainingAnswers(): array
    {
        return [
            'primary_goal' => 'leads',
            'value_proposition' => 'نتيجة قابلة للقياس خلال ثلاثين يومًا',
            'audience_clarity' => 'documented',
            'active_channels' => ['seo'],
            'tracking_maturity' => 'none',
            'content_cadence' => 'weekly',
            'landing_experience' => 'basic',
            'retention_motion' => 'manual',
            'sales_cycle' => 'medium',
            'known_cac' => 100,
        ];
    }
}
