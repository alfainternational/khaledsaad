<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolRunAnswer;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunPipeline;
use App\Services\Tools\ToolRunService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * تغذية التحليل: المنافسون الذين سمّاهم المستخدم يصلون إلى سياق الخلاصة
 * بالاسم، فيقارنه الذكاء الاصطناعي بهم لا بمنافسة مجرّدة.
 */
class CompetitorAiContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, ToolCatalogSeeder::class]);

        config()->set('ai.deepseek', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.deepseek.com',
            'model' => 'deepseek-v4-flash',
            'timeout' => 60,
            'tiers' => ['economy' => 'deepseek-v4-flash', 'standard' => 'deepseek-v4-flash', 'advanced' => 'deepseek-v4-flash'],
        ]);
    }

    #[Test]
    public function the_synthesis_prompt_carries_the_named_competitors(): void
    {
        Http::fake(function () {
            return Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => json_encode($this->validPayload(), JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 40],
            ]);
        });

        $run = $this->draftWithCompetitor('عسل الحاج، مناحل النيل');

        app(ToolRunPipeline::class)->handle($run);

        // المنافس المسمّى وصل إلى أحد نداءات الذكاء الاصطناعي (مرحلة الخلاصة).
        Http::assertSent(fn ($request) => str_contains($this->messagesText($request), 'عسل الحاج'));
    }

    #[Test]
    public function without_named_competitors_nothing_is_invented_in_the_prompt(): void
    {
        Http::fake(function () {
            return Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => json_encode($this->validPayload(), JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 40],
            ]);
        });

        $run = $this->draftWithCompetitor(null);
        app(ToolRunPipeline::class)->handle($run);

        // سياق المنافسين حاضر (الملاحظة موجودة) لكن قائمة الأسماء فارغة: لا اختلاق.
        Http::assertSent(function ($request) {
            $text = $this->messagesText($request);

            return str_contains($text, 'بمنافسيه المسمّين') && str_contains($text, '"named": []');
        });
    }

    /**
     * نص رسائل الطلب مفكوكًا من ترميز يونيكود، حتى تُقارَن العربية كما هي.
     */
    private function messagesText($request): string
    {
        return collect($request->data()['messages'] ?? [])->pluck('content')->implode("\n");
    }

    private function draftWithCompetitor(?string $names): ToolRun
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر عسل']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $svc = app(ToolRunService::class);
        $run = $svc->start($project, $tool, $user);

        $svc->saveStep($run, 1, ['business_model' => 'services', 'description' => str_repeat('وصف واضح للخدمة ', 3), 'geography' => 'الرياض', 'monthly_budget' => 5000]);
        $svc->saveStep($run, 2, ['primary_goal' => 'leads', 'value_proposition' => 'نسلّم خلال 48 ساعة أو المبلغ يُعاد كاملًا', 'audience_clarity' => 'documented']);
        $svc->saveStep($run, 3, ['active_channels' => ['seo', 'paid'], 'tracking_maturity' => 'basic', 'content_cadence' => 'weekly']);
        $svc->saveStep($run, 4, ['landing_experience' => 'basic', 'retention_motion' => 'manual', 'sales_cycle' => 'medium', 'known_cac' => 120]);

        if ($names !== null) {
            ToolRunAnswer::updateOrCreate(
                ['tool_run_id' => $run->id, 'field_key' => 'competitor_names'],
                ['value_json' => ['value' => $names], 'source' => ToolRunAnswer::SOURCE_USER],
            );
        }

        return $run->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'missing' => [], 'conflicts' => [], 'issues' => [],
            'headline' => 'عنوان تحليلي واضح للقسم',
            'points' => [['text' => 'نقطة تحليلية مبنية على الإجابات.', 'is_assumption' => false]],
            'summary' => 'ملخص تنفيذي يوضح الوضع الحالي وأهم ما يجب فعله في التسعين يومًا القادمة.',
            'confidence' => 70,
            'assumptions' => [],
            'next_step' => ['title' => 'ابدأ بالقياس', 'description' => 'عرّف حدث تحويل واحدًا واربطه بمصدر الزيارة هذا الأسبوع.'],
            'findings' => [
                [
                    'title' => 'القياس لا يصل إلى الإيراد',
                    'description' => 'التتبع الحالي يسجل الزيارات فقط فلا يُنسب أي ريال إلى قناة.',
                    'category' => 'القياس', 'severity' => 'high', 'evidence' => 'حالة القياس: أساسي',
                    'confidence' => 90, 'is_assumption' => false,
                    'recommendations' => [[
                        'title' => 'عرّف ثلاثة أحداث تحويل',
                        'description' => 'أضف أحداث النموذج وواتساب والشراء واربطها بمصدر الزيارة خلال أسبوعين.',
                        'impact' => 'high', 'effort' => 'low', 'kpi_hint' => 'عدد التحويلات المنسوبة',
                    ]],
                ],
                [
                    'title' => 'صفحة التحويل عامة',
                    'description' => 'الزيارات تصل إلى صفحة غير مخصصة للعرض المعلن عنه.',
                    'category' => 'التحويل', 'severity' => 'medium', 'is_assumption' => false,
                    'recommendations' => [[
                        'title' => 'أنشئ صفحة مخصصة',
                        'description' => 'صمّم صفحة واحدة لأهم عرض بوعد واضح وزر واحد لا يشتّت.',
                        'impact' => 'high', 'effort' => 'medium', 'kpi_hint' => 'نسبة التحويل',
                    ]],
                ],
                [
                    'title' => 'لا نظام احتفاظ',
                    'description' => 'لا متابعة منظمة بعد أول شراء.',
                    'category' => 'الاحتفاظ', 'severity' => 'medium', 'is_assumption' => false,
                    'recommendations' => [[
                        'title' => 'رسالة ما بعد الشراء',
                        'description' => 'أرسل شكرًا ثم عرضًا بعد أسبوعين لكل عميل جديد.',
                        'impact' => 'medium', 'effort' => 'low', 'kpi_hint' => 'نسبة العودة',
                    ]],
                ],
            ],
        ];
    }
}
