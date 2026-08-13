<?php

namespace Tests\Unit\Modules\Reporting;

use App\Modules\Reporting\Validation\SemanticValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SemanticValidatorTest extends TestCase
{
    #[Test]
    public function a_complete_report_passes_all_rules(): void
    {
        $this->assertTrue(app(SemanticValidator::class)->validate($this->valid())->passes());
    }

    #[Test]
    #[DataProvider('invalidCases')]
    public function it_reports_each_contract_rule(string $code, callable $mutate): void
    {
        $payload = $this->valid();
        $mutate($payload);
        $this->assertContains($code, app(SemanticValidator::class)->validate($payload)->codes());
    }

    public static function invalidCases(): array
    {
        return [
            'R01' => ['R01', fn (&$p) => $p['findings'][0]['recommendation']['template']['objective_id'] = 'other'],
            'R02' => ['R02', function (&$p) { $p['findings'][] = $p['findings'][0]; $p['findings'][1]['id'] = 2; }],
            'R03' => ['R03', fn (&$p) => $p['findings'][0]['recommendation']['steps'] = SemanticValidator::BOILERPLATE_STEPS],
            'R04' => ['R04', fn (&$p) => $p['findings'][0]['recommendation']['metric']['objective_id'] = 'other'],
            'R05' => ['R05', fn (&$p) => $p['findings'][0]['recommendation']['template']['blocks'] = [['value' => implode(' ', $p['findings'][0]['recommendation']['steps'])]]],
            'R06' => ['R06', fn (&$p) => $p['findings'][0]['recommendation']['expected_failure'] = 'اطلب تطوير المهمة للحصول على مثال لاحقًا'],
            'R07' => ['R07', fn (&$p) => $p['findings'][0]['recommendation']['template']['blocks'][] = ['value' => '[رقم سري مجهول]']],
            'R08' => ['R08', fn (&$p) => $p['findings'][0]['recommendation']['deliverable'] = ''],
            'R09' => ['R09', fn (&$p) => $p['findings'][0]['evidence']['answer_ref'] = ''],
            'R10' => ['R10', function (&$p) { $p['findings'][0]['evidence'] = ['answer_ref' => '', 'quote' => '']; $p['findings'][0]['is_assumption'] = false; }],
            'R11' => ['R11', fn (&$p) => $p['score']['value'] = 80],
            'R12' => ['R12', function (&$p) { $p['findings'][] = $p['findings'][0]; $p['findings'][0]['severity'] = 'low'; $p['findings'][1]['severity'] = 'high'; $p['findings'][1]['recommendation']['steps'][0] = 'افتح ملفًا مختلفًا وسجل المشكلة الأولى التي تراها بوضوح.'; }],
            'R13' => ['R13', function (&$p) { $p['provenance'] = 'signed'; $p['human_traces'] = []; }],
            'R14' => ['R14', fn (&$p) => $p['findings'][0]['recommendation']['duration_days'] = null],
            'R15' => ['R15', fn (&$p) => $p['findings'][0]['title'] = 'حسنConversionRateالآن'],
        ];
    }

    private function valid(): array
    {
        return [
            'provenance' => 'automated',
            'human_traces' => [],
            'score' => ['value' => 58, 'raw' => 58, 'max' => 100],
            'scoring' => [['points' => 58]],
            'known_placeholders' => ['اسم العميل'],
            'findings' => [[
                'id' => 1, 'title' => 'العرض يحتاج إلى وضوح', 'severity' => 'high', 'is_assumption' => false,
                'evidence' => ['answer_ref' => 'answer:12', 'quote' => 'لا نملك عرضًا موحدًا'],
                'recommendation' => [
                    'objective_id' => 'clarify-offer', 'title' => 'ثبّت العرض', 'rationale' => 'لتقليل التردد.',
                    'impact' => 'high', 'effort' => 'low', 'duration_days' => 3,
                    'metric' => ['label' => 'اكتمال العرض', 'objective_id' => 'clarify-offer'],
                    'steps' => ['اكتب النتيجة الأساسية للعميل في جملة واحدة واضحة.', 'أضف السعر والخطوة التالية ثم راجع الورقة مع عميل سابق.'],
                    'deliverable' => 'ورقة عرض مكتملة', 'done_when' => 'توجد ورقة يمكن فحصها بنعم أو لا.',
                    'first_five_minutes' => 'افتح مستندًا جديدًا واكتب عنوان النتيجة.',
                    'expected_failure' => 'قد تكثر المزايا؛ احذف ما لا يخدم النتيجة.',
                    'template' => ['objective_id' => 'clarify-offer', 'blocks' => [['value' => 'اسم المشروع: متجر أفق'], ['value' => '[اسم العميل]']]],
                ],
            ]],
        ];
    }
}
