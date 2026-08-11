<?php

namespace Tests\Unit\Modules\Execution;

use App\Modules\Execution\DeterministicExampleFactory;
use App\Modules\Execution\ExampleContext;
use App\Modules\Execution\RecommendationEnricher;
use App\Modules\Execution\WorkedExample;
use App\Modules\Shared\Sectors\Sector;
use Tests\TestCase;

/**
 * الضمان الذي جاء المُثري لأجله: لا توصية تصل بلا خطوة ولا بلا مثال.
 *
 * الاختبارات هنا بلا حاوية Laravel: المنطق حتمي بالكامل، وربطه بقاعدة
 * بيانات يخفي أنه كذلك.
 */
class RecommendationEnricherTest extends TestCase
{
    private RecommendationEnricher $enricher;

    private ExampleContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enricher = new RecommendationEnricher(new DeterministicExampleFactory);
        $this->context = new ExampleContext(
            businessName: 'مدارس الأفق',
            sector: Sector::EDUCATION,
            audience: 'أولياء أمور المرحلة الابتدائية',
            valueProposition: 'صفوف لا تتجاوز ١٥ طالبًا',
            geography: 'الرياض',
        );
    }

    public function test_ai_steps_and_example_are_kept_when_valid(): void
    {
        $result = $this->enricher->enrich([
            'title' => 'ابدأ بجمع أرقام أولياء الأمور',
            'description' => 'ليس لديك قائمة تواصل مباشرة، فكل تسجيل يبدأ من الصفر.',
            'action_steps' => [
                'افتح جدولًا جديدًا وسجّل فيه أرقام أولياء الأمور الحاليين.',
                'أرسل لكل واحد رسالة تعريف واحدة هذا الأسبوع.',
            ],
            'worked_example' => [
                'kind' => 'message',
                'title' => 'رسالة إلى ولي أمر مسجّل',
                'body' => 'السلام عليكم أستاذ [الاسم]، معك [اسمك] من مدارس الأفق. أردت أطمئنك على مستوى ابنك هذا الفصل.',
                'notes' => ['أرسلها بعد نهاية الدوام'],
            ],
        ], $this->context);

        $this->assertCount(2, $result['action_steps']);
        $this->assertSame('ai', $result['example_source']);
        $this->assertSame('رسالة إلى ولي أمر مسجّل', $result['worked_example']['title']);
        $this->assertSame('inferred', $result['worked_example']['evidence_level']);
    }

    /**
     * الحشو القديم: `action_steps = [description]`. خطوة تكرّر الوصف تجعل
     * التوصية تبدو ذات خطة وليس لها، فتُرفض ويُستبدل بأرضية حتمية.
     */
    public function test_step_that_only_repeats_the_description_is_rejected(): void
    {
        $description = 'حوّل العضوي إلى خطوات واضحة بدل النشر العشوائي.';

        $result = $this->enricher->enrich([
            'title' => 'رتّب المحتوى العضوي',
            'description' => $description,
            'action_steps' => [$description],
        ], $this->context);

        $this->assertNotSame([$description], $result['action_steps']);
        $this->assertGreaterThanOrEqual(2, count($result['action_steps']));
    }

    public function test_missing_example_falls_back_to_deterministic_and_declares_source(): void
    {
        $result = $this->enricher->enrich([
            'title' => 'اكتب رسالة تعريف لأولياء الأمور',
            'description' => 'التواصل المباشر عندك غير منظّم، فكل محاولة تبدأ من جديد.',
        ], $this->context);

        $this->assertSame('deterministic', $result['example_source']);
        $this->assertNotSame('', trim($result['worked_example']['body']));
        // المصدر يُعلَن حتى لا يُقرأ القالب المأمون كصياغة على حالة النشاط.
        $this->assertSame('deterministic', $result['worked_example']['source']);
    }

    /**
     * المثال يُنسخ ويُرسَل كما هو، فاختراع اسم داخله أخطر من اختراعه في
     * تحليل. القوالب الحتمية تترك فراغًا ظاهرًا بدل أي قيمة مؤلَّفة.
     */
    public function test_deterministic_example_leaves_visible_placeholders(): void
    {
        $result = $this->enricher->enrich([
            'title' => 'ابدأ التواصل المباشر مع قائمة أشخاص',
            'description' => 'لا يوجد مسار واضح من التعارف إلى الطلب.',
        ], $this->context);

        $this->assertStringContainsString('[', $result['worked_example']['body']);
    }

    /**
     * القطاع يبدّل لسان المثال: مدرسة تخاطب ولي أمر، ومتجر يخاطب مشتريًا.
     * مثالٌ يخاطب الأول بلسان الثاني يُهمَل ولا يُستعمل.
     */
    public function test_sector_changes_the_buyer_voice(): void
    {
        $education = $this->enricher->enrich(
            ['title' => 'ابدأ التواصل المباشر', 'description' => 'قائمة أشخاص بلا مسار واضح.'],
            $this->context,
        );

        $ecommerce = $this->enricher->enrich(
            ['title' => 'ابدأ التواصل المباشر', 'description' => 'قائمة أشخاص بلا مسار واضح.'],
            new ExampleContext(businessName: 'متجر ندى', sector: Sector::ECOMMERCE),
        );

        $this->assertStringContainsString('ولي الأمر', $education['worked_example']['title']);
        $this->assertStringContainsString('المشتري', $ecommerce['worked_example']['title']);
    }

    public function test_short_body_is_not_accepted_as_an_example(): void
    {
        $example = WorkedExample::fromPayload([
            'kind' => 'message',
            'title' => 'رسالة',
            'body' => 'اكتب رسالة تعريف.',
        ]);

        $this->assertNull($example);
    }
}
