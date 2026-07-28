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
}
