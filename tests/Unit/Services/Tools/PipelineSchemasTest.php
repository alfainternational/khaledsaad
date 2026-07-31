<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\PipelineSchemas;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PipelineSchemasTest extends TestCase
{
    #[Test]
    public function report_prompts_require_complete_human_language_for_the_reader(): void
    {
        $preamble = PipelineSchemas::systemPreamble();

        foreach ([
            'كل ما يحتاجه لفهم النتيجة واتخاذ القرار',
            'اكتب جملًا قصيرة',
            'ماذا تعني له',
            'ما الذي يستطيع فعله بعدها',
            'لا تعرض أسماء الحقول أو الأكواد الداخلية',
            'لا تكرر المعلومة داخل النتيجة الواحدة',
            'اكتب للقارئ المقصود',
            'لا يضطر قارئه للعودة إلى أداة أخرى',
        ] as $rule) {
            $this->assertStringContainsString($rule, $preamble);
        }

        $agencyReadiness = require dirname(__DIR__, 4).'/database/data/tools/agency-brief.php';
        $synthesis = $agencyReadiness['prompts']['synthesis'];

        $this->assertStringContainsString('لغة إنسانية', $synthesis);
        $this->assertStringContainsString('يفهم لماذا تهمه كل نتيجة', $synthesis);
        $this->assertStringContainsString('لا تنقل الحيرة الداخلية إلى موجز الوكالة', $synthesis);
        $this->assertStringContainsString('خاطب صاحب المشروع مباشرة بصيغة «أنت»', $synthesis);
        $this->assertStringContainsString('لا تعتبر الملف كاملًا إذا اضطر صاحبه للعودة', $synthesis);
    }

    #[Test]
    public function both_generation_paths_share_the_exact_same_classification_rubric(): void
    {
        // معيار واحد بنصّ واحد: أي انجراف بين المسار الآلي واليدوي يكسر
        // هذا الاختبار قبل أن يكسر اتساق التقارير.
        $rubric = PipelineSchemas::classificationRubric();

        $this->assertStringContainsString($rubric, PipelineSchemas::systemPreamble());

        $instructions = new \ReflectionMethod(\App\Services\Tools\ManualReportService::class, 'instructions');
        $service = (new \ReflectionClass(\App\Services\Tools\ManualReportService::class))
            ->newInstanceWithoutConstructor();

        $this->assertStringContainsString($rubric, $instructions->invoke($service));
    }

    #[Test]
    public function the_rubric_keeps_every_criterion_the_manual_path_used_to_lose(): void
    {
        $rubric = PipelineSchemas::classificationRubric();

        foreach ([
            'الخطورة severity',
            'الأثر impact',
            'الجهد effort',
            'الثقة confidence',
            'حدود القدرة المالية',
            'اتساق الاستنتاج',
            'المدخلات المعطوبة',
            'حالات الغياب الثلاث',
        ] as $criterion) {
            $this->assertStringContainsString($criterion, $rubric);
        }
    }
}
