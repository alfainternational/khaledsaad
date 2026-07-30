<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * القدرة بلا مدخل قدرةٌ غير موجودة.
 *
 * الاستقبال الصوتي كان مبنيًّا بمسار ومتحكّم ومزوّد خلف عقد، وبلا زر واحد
 * يستدعيه — أي نفس عطل الجسر الذي أُصلح في المرحلة ٢: كود صحيح لا يصل إليه
 * مستخدم.
 *
 * وموضعه ليس الاستشارة: كل أسئلتها اختيارات، والسؤال المفتوح — وهو ما يثقل
 * كتابته فيُترك فارغًا فتنخفض تغطية المحاور ١–٦ — يسكن في معالج الأدوات.
 */
class VoiceRecorderSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);

        // المفتاح مضبوط في الحالة الأساسية: بدونه لا مسجّل — وهو ما يفحصه
        // الاختبار الأخير صراحةً.
        config(['services.speech.key' => 'test-speech-key']);
    }

    #[Test]
    public function an_open_question_offers_to_record_the_answer(): void
    {
        [$user, $project, $run] = $this->startedRun();

        $response = $this->actingAs($user)
            ->get(route('app.runs.step', [$run->uuid, 1]))
            ->assertOk();

        // المدخل موجود ومربوط بمسار النسخ الحقيقي لهذا النشاط.
        $response->assertSee('data-voice', false);
        $response->assertSee(route('app.voice.store', $project->slug), false);
    }

    #[Test]
    public function the_recorder_never_submits_the_answer_by_itself(): void
    {
        [$user, , $run] = $this->startedRun();

        $html = $this->actingAs($user)
            ->get(route('app.runs.step', [$run->uuid, 1]))
            ->assertOk()
            ->getContent();

        /*
         * المراجعة شرط لا تحسين: النص يُملأ في الخانة ويُترك لصاحبه. إرسال
         * تلقائي يجعل خطأ نسخ في اسم أو رقم حقيقةً في الدماغ (§٤.٣).
         */
        $this->assertStringNotContainsString('form.submit()', $html);
        $this->assertStringContainsString('راجع النص قبل الإرسال', $html);
    }

    /**
     * زرٌّ يعِد بما لا تستطيعه المنصة أسوأ من غيابه.
     *
     * بلا مفتاح خدمة النسخ يسجّل المستخدم دقيقةً كاملة، ثم يقابل «لم يُضبط
     * المفتاح» فيقرؤه عطلًا في ميكروفونه أو في تسجيله — لا في إعداد لم يكتمل.
     * نفس منطق إخفاء الزر حيث لا يدعم المتصفح `MediaRecorder`.
     */
    #[Test]
    public function no_recorder_is_offered_while_the_service_is_unconfigured(): void
    {
        config(['services.speech.key' => null]);

        [$user, , $run] = $this->startedRun();

        $response = $this->actingAs($user)
            ->get(route('app.runs.step', [$run->uuid, 1]))
            ->assertOk();

        $response->assertDontSee('data-voice', false);
        $response->assertDontSee('سجّل إجابتك صوتيًّا', false);
    }

    /**
     * @return array{0: User, 1: Project, 2: ToolRun}
     */
    private function startedRun(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاطي']);

        $run = app(ToolRunService::class)->start(
            $project,
            Tool::where('key', 'marketing-score')->firstOrFail(),
            $user,
        );

        return [$user, $project->fresh(), $run];
    }
}
