<?php

namespace Tests\Unit\Modules\Reporting;

use App\Modules\Reporting\Contracts\RecommendationContractBuilder;
use Database\Seeders\ReportingContractSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecommendationContractBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReportingContractSeeder::class);
    }

    #[Test]
    public function it_builds_the_complete_contract_with_exact_objective_links(): void
    {
        $candidate = app(RecommendationContractBuilder::class)->build([
            'objective_id' => 'clarify-offer',
            'title' => 'ثبّت العرض في ورقة واحدة',
            'description' => 'اجمع النتيجة والسعر والطمأنة والخطوة التالية في عرض واحد.',
            'deliverable' => 'ورقة عرض مكتملة',
            'done_when' => 'يمكن لشخص من الجمهور شرح العرض بعد قراءته مرة واحدة.',
            'first_five_minutes' => 'افتح مستندًا جديدًا واكتب النتيجة التي يشتريها العميل.',
            'expected_failure' => 'قد تكتب مزايا كثيرة؛ احذف كل ميزة لا تشرح النتيجة.',
            'duration_days' => 3,
            'impact' => 'high',
            'effort' => 'low',
            'metric' => ['label' => 'عدد المحادثات التي وصلت إلى طلب سعر', 'objective_id' => 'clarify-offer'],
            'action_steps' => [
                'اكتب النتيجة التي يشتريها العميل في جملة واحدة واضحة.',
                'أضف السعر والطمأنة والخطوة التالية ثم اعرض الورقة على عميل سابق.',
            ],
        ], 'offer-builder', 'offer', ['project' => ['name' => 'متجر أفق']]);

        $this->assertFalse($candidate->degraded);
        $this->assertSame('clarify-offer', $candidate->objectiveId);
        $this->assertSame('clarify-offer', $candidate->metricObjectiveId);
        $this->assertSame('clarify-offer', $candidate->template['objective_id']);
        $this->assertNotEmpty($candidate->deliverable);
        $this->assertNotEmpty($candidate->doneWhen);
        $this->assertNotEmpty($candidate->firstFiveMinutes);
        $this->assertNotEmpty($candidate->expectedFailure);
    }

    #[Test]
    public function missing_required_contract_data_is_degraded_not_filled_with_generic_steps(): void
    {
        $candidate = app(RecommendationContractBuilder::class)->build([
            'title' => 'حسّن العرض',
            'description' => 'وصف غير كافٍ.',
            'impact' => 'medium',
            'effort' => 'medium',
        ], 'offer-builder', 'offer', ['project' => ['name' => 'متجر أفق']]);

        $this->assertTrue($candidate->degraded);
        $this->assertContains('missing_contract_field', $candidate->degradeReasons);
        $this->assertSame([], $candidate->actionSteps);
        $this->assertNotSame([], $candidate->fallbackCoaching);
    }

    #[Test]
    public function a_missing_exact_template_is_explicit_and_never_substituted(): void
    {
        $candidate = app(RecommendationContractBuilder::class)->build([
            'objective_id' => 'clarify-offer',
            'title' => 'ثبّت العرض',
            'description' => 'اكتب عرضًا واحدًا يمكن اختباره.',
            'deliverable' => 'ورقة عرض مكتملة',
            'done_when' => 'توجد ورقة قابلة للمراجعة.',
            'first_five_minutes' => 'افتح مستندًا واكتب عنوان العرض.',
            'expected_failure' => 'قد يتسع النطاق؛ التزم بعرض واحد.',
            'duration_days' => 2,
            'impact' => 'high',
            'effort' => 'low',
            'metric' => ['label' => 'اكتمال الورقة', 'objective_id' => 'clarify-offer'],
            'action_steps' => ['اكتب النتيجة الأساسية في أعلى الورقة بوضوح.', 'راجع الورقة مع عميل سابق وسجل اعتراضه الأول.'],
        ], 'offer-builder', 'offer', ['project' => []]);

        $this->assertTrue($candidate->degraded);
        $this->assertNull($candidate->template);
        $this->assertContains('missing_template', $candidate->degradeReasons);
    }

    #[Test]
    public function an_objective_outside_the_tool_catalog_is_rejected(): void
    {
        $candidate = app(RecommendationContractBuilder::class)->build([
            'objective_id' => 'competitor-analysis',
            'title' => 'عنوان',
        ], 'offer-builder', 'offer', ['project' => ['name' => 'متجر أفق']]);

        $this->assertTrue($candidate->degraded);
        $this->assertContains('objective_not_allowed', $candidate->degradeReasons);
    }
}
