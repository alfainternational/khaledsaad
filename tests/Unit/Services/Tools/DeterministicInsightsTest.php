<?php

namespace Tests\Unit\Services\Tools;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\ToolVersion;
use App\Services\Tools\DeterministicInsights;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الأرضية الحتمية: حين يصمت الذكاء الاصطناعي، هذه الطبقة وحدها تضمن
 * أن يخرج العميل بأولويات حقيقية من درجته، مرتبة بالأثر.
 */
class DeterministicInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function it_ranks_the_weakest_high_impact_dimensions_first(): void
    {
        $run = $this->makeRun('marketing-score');

        // بند مرتفع الوزن ومنخفض العائد يجب أن يتصدّر، والقوي لا يظهر أصلًا.
        $baseline = [
            'score' => 40,
            'band' => 'يحتاج تركيزًا',
            'breakdown' => [
                ['field' => 'tracking_maturity', 'label' => 'نضج القياس', 'weight' => 18, 'factor' => 0.0],
                ['field' => 'value_proposition', 'label' => 'وضوح العرض', 'weight' => 12, 'factor' => 1.0],
                ['field' => 'landing_experience', 'label' => 'جاهزية صفحات التحويل', 'weight' => 14, 'factor' => 0.5],
            ],
        ];

        $findings = app(DeterministicInsights::class)->findings($run, $baseline, 3);

        // البند مكتمل العائد (factor=1) لا فجوة له فلا يظهر.
        $this->assertCount(2, $findings);
        // الأعلى فجوة (18 × 1.0) أولًا.
        $this->assertStringContainsString('من أين يأتي عملاؤك', $findings[0]['title']);
        $this->assertSame('high', $findings[0]['severity']);
    }

    #[Test]
    public function every_finding_carries_at_least_one_actionable_recommendation(): void
    {
        $run = $this->makeRun('funnel-audit');

        $baseline = [
            'score' => 30,
            'band' => 'ضعيف',
            'breakdown' => [
                ['field' => 'response_time', 'label' => 'سرعة الرد', 'weight' => 16, 'factor' => 0.0],
                ['field' => 'followup_system', 'label' => 'نظام المتابعة', 'weight' => 16, 'factor' => 0.0],
            ],
        ];

        $findings = app(DeterministicInsights::class)->findings($run, $baseline, 5);

        $this->assertNotEmpty($findings);

        foreach ($findings as $finding) {
            $this->assertNotEmpty($finding['recommendations']);
            $this->assertArrayHasKey('title', $finding['recommendations'][0]);
            $this->assertArrayHasKey('description', $finding['recommendations'][0]);
            // حتمية لا افتراضية: مبنية على إجابات العميل ودرجته.
            $this->assertFalse($finding['is_assumption']);
        }
    }

    #[Test]
    public function it_uses_customer_language_advice_from_the_tool_definition(): void
    {
        $run = $this->makeRun('marketing-score');

        $baseline = [
            'score' => 20,
            'band' => 'ضعيف',
            'breakdown' => [
                ['field' => 'value_proposition', 'label' => 'وضوح العرض', 'weight' => 12, 'factor' => 0.0],
            ],
        ];

        $finding = app(DeterministicInsights::class)->findings($run, $baseline, 1)[0];

        // النص من weak_advice في تعريف الأداة، لا صياغة عامة.
        $this->assertStringContainsString('سبب الشراء منك', $finding['title']);
        $this->assertNotEmpty($finding['recommendations'][0]['kpi_hint']);
    }

    private function makeRun(string $toolKey): ToolRun
    {
        $tool = Tool::where('key', $toolKey)->firstOrFail();
        $version = ToolVersion::where('tool_id', $tool->id)->firstOrFail();

        $run = new ToolRun(['tool_version_id' => $version->id, 'status' => ToolRun::STATUS_DRAFT]);
        $run->setRelation('toolVersion', $version->load('tool'));

        return $run;
    }
}
