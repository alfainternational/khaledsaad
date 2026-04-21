<?php

namespace Tests\Unit;

use App\Domain\AI\Models\AITemplate;
use App\Support\AI\StudioTemplateContractRegistry;
use App\Support\AI\StudioTemplateOutputValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudioTemplateOutputValidatorTest extends TestCase
{
    #[Test]
    public function it_flags_cross_template_contamination_in_brand_positioning_output(): void
    {
        $validator = new StudioTemplateOutputValidator(new StudioTemplateContractRegistry);
        $template = new AITemplate([
            'code' => 'brand-positioning',
            'output_contract_json' => [
                'sections' => [
                    'المنفّذون المستهدفون',
                    'تعريف الفئة والإطار',
                    'أسباب الثقة',
                    'رسالة موضع جاهزة للاستخدام + Elevator pitch + نسخة قصيرة للموقع',
                ],
            ],
        ]);

        $output = <<<'TEXT'
## المنفّذون المستهدفون
- كاتب محتوى

## تعريف الفئة والإطار
هذا المشروع يساعد المشاريع الصغيرة بشكل عام.

## رسالة موضع جاهزة للاستخدام + Elevator pitch + نسخة قصيرة للموقع
نحن مشروع رائع يخدم الجميع.

## توجيات للمصمم
- مقاسات 1:1 و 9:16

## جدول واتساب
| الرسالة | النص الكامل | التوقيت |
| --- | --- | --- |
| رسالة 1 | ... | 10:00 |
TEXT;

        $issues = $validator->issuesFor($output, $template->output_contract_json, $template);

        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'تسرّبت')));
        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'placeholders')));
        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'أسباب الثقة')));
    }

    #[Test]
    public function it_flags_generic_brand_positioning_that_lacks_strategy_assets(): void
    {
        $validator = new StudioTemplateOutputValidator(new StudioTemplateContractRegistry);
        $template = new AITemplate([
            'code' => 'brand-positioning',
            'output_contract_json' => [
                'sections' => [
                    'المنفّذون المستهدفون',
                    'Segment + Moment + Unique Mechanism (الفئة الدقيقة + لحظة الاحتياج + آلية/زاوية التميز)',
                    'Positioning الداخلي بصيغته الكاملة',
                    'الرسائل الجاهزة: Elevator pitch + نسخة قصيرة للموقع + رسالة بيع افتتاحية',
                    'Framework العمل: كيف نعمل من التشخيص إلى الرسالة إلى الاختبار',
                ],
            ],
        ]);

        $output = <<<'TEXT'
## المنفّذون المستهدفون
- كاتب محتوى: يراجع الملف ويكتب منه.

## Positioning الداخلي بصيغته الكاملة
Positioning الداخلي: نحن نساعد المشاريع الصغيرة على التسويق بشكل أفضل وتقديم حلول تسويق عملية لكل العملاء.

## لماذا نحن (Value Proposition + زاوية التميز)
Value Proposition: نقدم حلول تسويق عملية وسريعة.

## أسباب الثقة (Reasons to believe)
- نسبة نجاح عالية
- عملاء يثقون بنا

## الرسائل الجاهزة: Elevator pitch + نسخة قصيرة للموقع + رسالة بيع افتتاحية
Elevator pitch: نساعد المشاريع الصغيرة على التسويق بشكل أفضل.
نسخة قصيرة للموقع: نساعد المشاريع الصغيرة على التسويق بشكل أفضل.
رسالة بيع افتتاحية: نساعد المشاريع الصغيرة على التسويق بشكل أفضل.

## لمن لا نخدم ولماذا
إدارة محتوى شهرية وشراكة نمو

## Framework العمل: كيف نعمل من التشخيص إلى الرسالة إلى الاختبار
تحليل
تنفيذ
تحسين
TEXT;

        $issues = $validator->issuesFor($output, $template->output_contract_json, $template);

        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'Segment وMoment وUnique Mechanism')));
        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'Framework')));
        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'المشاريع الصغيرة')));
        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'Commodity')));
        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'يعيد نفس الرسالة')));
    }
}
