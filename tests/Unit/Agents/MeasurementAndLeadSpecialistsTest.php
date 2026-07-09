<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\LeadQualitySpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\MeasurementPlanSpecialist;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MeasurementAndLeadSpecialistsTest extends TestCase
{
    #[Test]
    public function measurement_flags_missing_control_group_as_high(): void
    {
        $r = (new MeasurementPlanSpecialist)->analyze('سنطلق الحملة ونقارن المبيعات بالشهر الماضي.');
        $control = collect($r['findings'])->firstWhere('code', 'no_control');

        $this->assertNotNull($control);
        $this->assertSame('high', $control['severity']);
    }

    #[Test]
    public function rigorous_measurement_plan_scores_high(): void
    {
        $r = (new MeasurementPlanSpecialist)->analyze(
            'نستخدم مجموعة ضابطة (holdout) مقابل خط أساس، بحجم عينة كافٍ ومستوى دلالة 95%، لقياس الأثر التزايدي.',
        );

        $this->assertGreaterThanOrEqual(85, $r['score']);
    }

    #[Test]
    public function lead_flags_missing_consent_as_high(): void
    {
        $r = (new LeadQualitySpecialist)->analyze('نجمع أرقام هواتف من قوائم عامة ونتصل بها.');
        $consent = collect($r['findings'])->firstWhere('code', 'no_consent');

        $this->assertNotNull($consent);
        $this->assertSame('high', $consent['severity']);
    }

    #[Test]
    public function strong_lead_criteria_scores_high(): void
    {
        $r = (new LeadQualitySpecialist)->analyze(
            'ليد وافق عبر opt-in، يتطابق مع العميل المثالي، لديه ميزانية وسلطة قرار وحاجة، ومصدره حملة إعلانية بـUTM.',
        );

        $this->assertGreaterThanOrEqual(85, $r['score']);
    }
}
