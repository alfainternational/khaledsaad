<?php

namespace Tests\Unit\Reports;

use App\Application\Reports\StrategicDiagnosisBuilder;
use App\Domain\Tool\Models\ToolRun;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StrategicDiagnosisBuilderTest extends TestCase
{
    /**
     * @param  array<string, array<string, string>>  $toolInputs  tool_code => inputs
     */
    private function runs(array $toolInputs): Collection
    {
        return collect($toolInputs)->map(function (array $inputs, string $code): ToolRun {
            $run = new ToolRun;
            $run->tool_code = $code;
            $run->inputs_json = $inputs;

            return $run;
        })->values();
    }

    #[Test]
    public function it_detects_message_gap_between_known_advantage_and_generic_positioning(): void
    {
        $result = (new StrategicDiagnosisBuilder)->build($this->runs([
            'competitor-analysis' => ['own_advantage' => 'ثبات 12 ساعة بسعر أقل 30% من المستورد'],
            'positioning' => ['main_difference' => 'جودة عالية'],
        ]));

        $problem = collect($result['problems'])->first(fn ($p) => str_contains($p['problem'], 'رسالتك'));
        $this->assertNotNull($problem, 'يجب كشف فجوة الرسالة');
        $this->assertStringContainsString('ثبات 12 ساعة', $problem['cause']); // السبب يستشهد بالميزة الفعلية
        $this->assertNotSame('', $problem['solution']);
        $this->assertSame('high', $problem['severity']);
    }

    #[Test]
    public function every_problem_carries_a_cause_and_a_solution(): void
    {
        $result = (new StrategicDiagnosisBuilder)->build($this->runs([
            'ideal-customer' => ['customer_type' => 'الجميع'],
            'customer-journey' => ['journey_doubt' => 'يتردد بسبب الثقة والسعر'],
            'offer-builder' => ['offer_result' => 'عطر يدوم طويلاً', 'offer_guarantee' => ''],
        ]));

        $this->assertNotEmpty($result['problems']);
        foreach ($result['problems'] as $p) {
            $this->assertNotSame('', $p['problem']);
            $this->assertNotSame('', $p['cause']);
            $this->assertNotSame('', $p['solution']);
            $this->assertContains($p['severity'], ['high', 'mid', 'low']);
        }
    }

    #[Test]
    public function it_detects_missing_risk_reversal_when_hesitation_exists(): void
    {
        $result = (new StrategicDiagnosisBuilder)->build($this->runs([
            'customer-journey' => ['journey_doubt' => 'العميلة تتردّد بسبب الثقة'],
            'offer-builder' => ['offer_name' => 'باقة'],
        ]));

        $this->assertTrue(
            collect($result['problems'])->contains(fn ($p) => str_contains($p['problem'], 'يتردّد')),
        );
    }

    #[Test]
    public function a_strong_covered_project_produces_fewer_problems(): void
    {
        $result = (new StrategicDiagnosisBuilder)->build($this->runs([
            'competitor-analysis' => ['own_advantage' => 'ثبات 12 ساعة بنصف السعر'],
            'positioning' => ['main_difference' => 'العطر الوحيد الذي يدوم 12 ساعة بنصف سعر المستورد في السعودية'],
            'ideal-customer' => ['customer_type' => 'نساء 28-40 في الرياض يشترين هدايا فاخرة'],
            'offer-builder' => ['offer_result' => 'ثبات مضمون', 'offer_guarantee' => 'إرجاع خلال 14 يوماً'],
            'value-ladder' => ['ladder_retention' => 'اشتراك شهري'],
            'kpi-tracker' => ['kpi_leading' => 'معدّل التحويل'],
        ]));

        // مشروع مكتمل الإجابات القوية: لا فجوة رسالة ولا مخاطرة ولا احتفاظ ولا شريحة غامضة.
        $codes = collect($result['problems'])->pluck('problem');
        $this->assertFalse($codes->contains(fn ($p) => str_contains($p, 'رسالتك')));
        $this->assertFalse($codes->contains(fn ($p) => str_contains($p, 'احتفاظ')));
    }

    #[Test]
    public function it_reports_missing_domains(): void
    {
        $result = (new StrategicDiagnosisBuilder)->build($this->runs([
            'positioning' => ['main_difference' => 'شيء'],
        ]));

        $this->assertContains('العميل المثالي', $result['missing']);
        $this->assertContains('المؤشرات', $result['missing']);
    }
}
