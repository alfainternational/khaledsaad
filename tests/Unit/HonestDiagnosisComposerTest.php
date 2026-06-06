<?php

namespace Tests\Unit;

use App\Support\Intelligence\HonestDiagnosisComposer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HonestDiagnosisComposerTest extends TestCase
{
    #[Test]
    public function it_does_not_invent_a_broad_plan_when_analysis_coverage_is_insufficient(): void
    {
        $composer = new HonestDiagnosisComposer;

        $report = $composer->compose(
            [
                'website' => 48,
                'social' => 35,
                'seo' => 42,
                'trust' => 30,
                'conversion' => 28,
                'ads_readiness' => 25,
                'ai_visibility' => 20,
                'competition' => 55,
                'lead_readiness' => 33,
            ],
            [
                [
                    'title' => 'الموقع غير قابل للوصول حالياً',
                    'recommendation' => 'تحقق من الدومين والاستضافة ثم أعد التحليل.',
                    'score_impact' => 24,
                    'confidence' => 0.95,
                ],
            ],
            [],
            [],
            [],
            [
                'status' => 'insufficient',
                'warnings' => [
                    'تعذر الوصول إلى الموقع الأساسي أو قراءة الصفحة الرئيسية بشكل كافٍ.',
                    'لا توجد روابط سوشيال رسمية مؤكدة ضمن المشروع.',
                ],
                'competitor_summary' => 'لا توجد بيانات منافسين مؤكدة تكفي لصناعة snapshot موثوق.',
            ],
        );

        $this->assertSame([], $report['priority_actions']['improvements_30_days']);
        $this->assertSame([], $report['priority_actions']['strategic_90_days']);
        $this->assertStringContainsString('لا توجد تغطية كافية', $report['honest_diagnosis'][0]);
        $this->assertSame(
            'لا توجد بيانات منافسين مؤكدة تكفي لصناعة snapshot موثوق.',
            $report['competitor_snapshot']['summary'],
        );
    }

    #[Test]
    public function it_keeps_actionable_outputs_when_analysis_has_verified_coverage(): void
    {
        $composer = new HonestDiagnosisComposer;

        $report = $composer->compose(
            [
                'website' => 78,
                'social' => 61,
                'seo' => 59,
                'trust' => 63,
                'conversion' => 51,
                'ads_readiness' => 54,
                'ai_visibility' => 58,
                'competition' => 66,
                'lead_readiness' => 71,
            ],
            [
                [
                    'title' => 'الصفحة الأساسية بلا H1 واضح',
                    'recommendation' => 'أضف H1 واحداً يشرح الرسالة الأساسية للصفحة.',
                    'score_impact' => 7,
                    'confidence' => 0.86,
                ],
                [
                    'title' => 'لا يوجد CTA واضح في المحتوى الظاهر',
                    'recommendation' => 'أضف CTA مباشر في الهيرو وأعد تكراره في الأقسام الأساسية.',
                    'score_impact' => 16,
                    'confidence' => 0.88,
                ],
                [
                    'title' => 'إشارات السياسات والثقة ناقصة',
                    'recommendation' => 'أظهر روابط الخصوصية والشروط بوضوح في الفوتر.',
                    'score_impact' => 9,
                    'confidence' => 0.81,
                ],
            ],
            [
                [
                    'label' => 'Competitor A',
                    'executive_score' => 82,
                ],
            ],
            [
                [
                    'contact_type' => 'official_email',
                    'contact_value' => 'info@example.com',
                    'is_verified' => true,
                ],
            ],
            [],
            [
                'status' => 'verified',
                'warnings' => [],
            ],
        );

        $this->assertNotEmpty($report['priority_actions']['quick_wins_7_days']);
        $this->assertNotEmpty($report['priority_actions']['improvements_30_days']);
        $this->assertNotEmpty($report['priority_actions']['strategic_90_days']);
        $this->assertNotEmpty($report['competitor_snapshot']['leaders']);
    }
}
