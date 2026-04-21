<?php

namespace Tests\Unit;

use App\Domain\AI\Models\AITemplate;
use App\Support\AI\StudioOutputQualityGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudioOutputQualityGuardTest extends TestCase
{
    #[Test]
    public function it_flags_generic_and_thin_studio_output(): void
    {
        $guard = new StudioOutputQualityGuard;
        $template = new AITemplate([
            'output_contract_json' => [
                'sections' => [
                    'المنفّذون المستهدفون',
                    'جدول النسخ الإعلانية',
                    'قائمة تحقق قبل النشر',
                ],
            ],
        ]);

        $issues = $guard->issuesFor(<<<'TEXT'
        ## مقدمة
        سوف يتم العمل على تحسين الحملة، ويمكن التركيز على الجمهور المناسب.
        من المهم تحسين الرسائل، وسيتم تطوير النص لاحقاً.
        TEXT, $template->output_contract_json);

        $this->assertNotEmpty($issues);
        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'قصير')));
        $this->assertTrue(collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'لغة عامة')));
    }

    #[Test]
    public function it_accepts_structured_execution_ready_output(): void
    {
        $guard = new StudioOutputQualityGuard;
        $template = new AITemplate([
            'output_contract_json' => [
                'sections' => [
                    'المنفّذون المستهدفون',
                    'ملخص الحملة',
                    'قائمة تحقق قبل النشر',
                ],
            ],
        ]);

        $output = <<<'TEXT'
        ## المنفّذون المستهدفون
        - مدير الإعلانات: ينسخ الحقول الجاهزة كما هي إلى Meta Ads Manager ويبدّل الرابط النهائي فقط.
        - المصمم: يعتمد زوايا الإبداع والنصوص المختصرة داخل الصورة دون إعادة صياغة العرض.

        ## ملخص الحملة
        الحملة تستهدف أصحاب المشاريع الصغيرة في الرياض ممن لديهم طلب غير مستقر ويحتاجون عرضاً واضحاً يرفع الرسائل المؤهلة. هدف المرحلة الأولى هو الرسائل، والجمهور المقترح هو أصحاب الأنشطة الخدمية الذين لديهم قرار شراء سريع.

        ## النسخ الإعلانية الجاهزة
        ### النسخة الأولى
        النص الأساسي: "عندك خدمة ممتازة لكن العميل لا يفهم لماذا يختارك؟ هذه المشكلة ليست في جودة الخدمة بل في طريقة تقديم العرض. خلال 7 أيام نعيد ترتيب الرسالة والعرض والمتابعة لتعرف بالضبط ماذا تقول، وكيف تقنع، وماذا ترسل بعد أول تواصل. احجز جلسة البداية الآن."
        العنوان: "حوّل خدمتك إلى عرض واضح يجلب رسائل مؤهلة"
        الوصف: "مناسب للخدمات التي تعتمد على الثقة وسرعة الإغلاق"
        CTA: "احجز الآن"

        ### النسخة الثانية
        النص الأساسي: "إذا كنت تنشر باستمرار لكن الرسائل ضعيفة، فالمشكلة غالباً ليست في عدد المنشورات بل في زاوية الخطاب. نساعدك على صياغة عرض يُفهم بسرعة ويُشعِر العميل أن القرار منطقي وآمن. ابدأ بمراجعة سريعة ونبني لك الخطة التنفيذية المناسبة."
        العنوان: "رسائل أكثر لأن عرضك صار أوضح"
        الوصف: "مراجعة سريعة ثم تنفيذ مباشر"
        CTA: "راسلنا"

        ## قائمة تحقق قبل النشر
        - تأكد من وضع رابط واتساب أو صفحة الهبوط النهائي داخل الإعلان.
        - راجع اسم النشاط في أول سطر من الصفحة حتى يتطابق مع العرض داخل الإعلان.
        - استخدم مقاس 4:5 للنسخ الأساسية و9:16 للستوري.

        ## كيف تقيس النجاح وما تعدّله إن لم تتحقق الأهداف
        راقب تكلفة الرسالة المؤهلة خلال أول 5 أيام. إذا كانت الرسائل موجودة لكن الجودة منخفضة، عدّل أول سطر في النسخة الأولى ليذكر نوع العميل المستهدف بوضوح أكبر. إذا كانت النقرات ضعيفة من الأساس، اختبر عنواناً أكثر مباشرة يركز على النتيجة الزمنية.
        TEXT;

        $issues = $guard->issuesFor($output, $template->output_contract_json);

        $this->assertSame([], $issues);
    }

    #[Test]
    public function system_prompt_includes_strategic_requirements_for_brand_positioning(): void
    {
        $guard = new StudioOutputQualityGuard;
        $template = new AITemplate([
            'code' => 'brand-positioning',
            'system_role' => 'أنت استراتيجي براند.',
        ]);

        $prompt = $guard->systemPrompt($template);

        $this->assertStringContainsString('متطلبات التفكير الاستراتيجي', $prompt);
        $this->assertStringContainsString('Unique Mechanism', $prompt);
        $this->assertStringContainsString('تحسين الحضور', $prompt);
    }
}
