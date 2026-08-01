<?php

namespace Tests\Feature;

use App\Models\AnswerFitness;
use App\Models\Project;
use App\Models\User;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Intake\Assist\ProfileQuestions;
use App\Modules\Intake\Fitness\AnswerFitnessScorer;
use App\Modules\Reporting\AgencyReportService;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * كل باب يدخل منه مدخلٌ إلى الدماغ يُقاس، وكل سؤال يُعان.
 *
 * القاعدة التي تحرسها هذه الحزمة: **نفس الحقيقة تأخذ نفس الحكم مهما كان الباب**.
 * ملف المشروع وموجز الوكالة كانا بابين يكتبان في الدماغ بلا مرور بطبقة القياس،
 * فتأخذ «قيمتي إني الأفضل» وزنها كاملًا في المحور إن كُتبت في الملف، وتُقاس إن
 * كُتبت داخل أداة. اختلاف الدرجة بحسب موضع الكتابة لا يمكن الدفاع عنه أمام
 * صاحب النشاط، ولا شرحه في تقرير.
 */
class InputIntelligenceSurfacesTest extends TestCase
{
    use RefreshDatabase;

    // ——— ملف المشروع ———

    #[Test]
    public function the_project_profile_is_measured_like_any_other_door(): void
    {
        [$user, $project] = $this->owned();

        app(ProjectService::class)->updateProfile($project, [
            'value_proposition' => 'أفضل واحد',
            'description' => 'نبيع دورات تدريبية أونلاين لموظفي شركات في الرياض يحتاجون شهادات معتمدة للترقية.',
        ]);

        $weak = AnswerFitness::where('project_id', $project->id)->where('field_key', 'value_proposition')->first();
        $strong = AnswerFitness::where('project_id', $project->id)->where('field_key', 'description')->first();

        $this->assertNotNull($weak, 'ملف المشروع ما زال بابًا يفلت من القياس.');
        $this->assertNotNull($strong);
        $this->assertSame(AnswerFitness::VERDICT_INSUFFICIENT, $weak->verdict);
        $this->assertSame(AnswerFitness::VERDICT_SUFFICIENT, $strong->verdict);
    }

    /**
     * إجابة صحيحة قصيرة لا تُعاقَب.
     *
     * كان التوقّع العام يفرض «من هم» و«لماذا يشترون» على **كل** حقل مفتوح، فكانت
     * «الرياض» جوابًا عن «أين عملاؤك؟» تنزل إلى أدنى معامل وتخفض محور البنية
     * القنواتية — بإجابة لا عيب فيها. الخطأ في اتجاه العقوبة أسوأ من الخطأ في
     * اتجاه التساهل: هذا يدفع صاحب النشاط إلى إصلاح ما ليس مكسورًا.
     */
    #[Test]
    public function a_legitimately_short_answer_is_not_punished_by_the_general_expectation(): void
    {
        $scorer = app(AnswerFitnessScorer::class);

        $this->assertSame(AnswerFitness::VERDICT_SUFFICIENT, $scorer->evaluate('geography', 'الرياض')->verdict);
        $this->assertSame(AnswerFitness::VERDICT_SUFFICIENT, $scorer->evaluate('industry', 'مدارس أهلية')->verdict);

        // والعموم يظل معاقَبًا حتى في الحقول القصيرة: «الخليج كله» ليست العيب،
        // بل «كل مكان» التي لا تحدّد سوقًا.
        $this->assertNotSame(AnswerFitness::VERDICT_SUFFICIENT, $scorer->evaluate('geography', 'عام')->verdict);
    }

    #[Test]
    public function project_identifiers_and_choices_are_never_graded(): void
    {
        // الاسم مُعرِّف والمرحلة اختيار: الحكم عليهما يخلق رقمًا بلا معنى.
        $this->assertNull(ProfileQuestions::measurableType('name'));
        $this->assertNull(ProfileQuestions::measurableType('stage'));
        $this->assertNull(ProfileQuestions::measurableType('sector'));
        $this->assertNull(ProfileQuestions::measurableType('monthly_budget'));

        $this->assertSame('textarea', ProfileQuestions::measurableType('value_proposition'));
        $this->assertSame('text', ProfileQuestions::measurableType('geography'));

        // ومفتاح ليس من الملف أصلًا لا يُقاس بالصدفة.
        $this->assertNull(ProfileQuestions::measurableType('مفتاح_مخترع'));
    }

    #[Test]
    public function a_vague_profile_answer_lowers_the_axis_it_feeds(): void
    {
        [, $weakProject] = $this->owned();
        [, $strongProject] = $this->owned();

        app(ProjectService::class)->updateProfile($weakProject, ['value_proposition' => 'أفضل واحد']);
        app(ProjectService::class)->updateProfile($strongProject, [
            'value_proposition' => 'أوصّل في اليوم نفسه بينما يحتاج غيري ثلاثة أيام، فالعميل المستعجل لا يضطر للانتظار.',
        ]);

        $scorer = app(AxisScorer::class);
        $weak = $scorer->score($weakProject->fresh(), Axis::StrategicClarity);
        $strong = $scorer->score($strongProject->fresh(), Axis::StrategicClarity);

        $this->assertSame($strong->coverage, $weak->coverage, 'المدخل موجود في الحالتين — التغطية واحدة.');
        $this->assertLessThan($strong->score, $weak->score);
    }

    #[Test]
    public function every_profile_question_offers_assistance(): void
    {
        [$user, $project] = $this->owned();

        $html = $this->actingAs($user)
            ->get(route('app.projects.edit', $project))
            ->assertOk()
            ->getContent();

        foreach (ProfileQuestions::fields() as $field) {
            $this->assertStringContainsString(
                'data-question-key="'.$field['key'].'"',
                $html,
                "سؤال الملف {$field['key']} بلا مساعدة.",
            );
        }

        $this->assertStringContainsString('data-surface="profile"', $html);
    }

    /**
     * لا مساعدة على شاشة الإنشاء: المقترح يُبنى على ما نعرفه، ولا نشاط بعد.
     * زرٌّ هناك يُنتج كلامًا عامًّا لا يستحق استعلامًا مدفوعًا.
     */
    #[Test]
    public function the_create_screen_does_not_offer_assistance_it_cannot_ground(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('app.projects.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-assist', $html);
    }

    // ——— موجز الوكالة ———

    #[Test]
    public function the_agency_brief_feeds_the_brain_instead_of_sitting_in_a_json_column(): void
    {
        [, $project] = $this->owned();

        app(AgencyReportService::class)->saveBrief($project, [
            'primary_goal' => 'leads',
            'success_metric' => 'الجميع',
            'what_works_now' => 'الإحالات من عملاء حاليين في الرياض تجلب أغلب الطلبات، والإعلان المدفوع لم ينجح.',
        ]);

        $facts = $project->brainFacts()->pluck('key')->all();

        /*
         * `primary_goal` مدخل في `AxisRegistry`، وحساب المحور يقرأ من الدماغ لا
         * من `project_profiles`. بقاؤه في العمود وحده كان يعني أن مستخدمًا أجاب
         * عن سؤال الهدف ثم رأى مدخله «غائبًا» في تقريره.
         */
        $this->assertContains('primary_goal', $facts);
        $this->assertContains('what_works_now', $facts);

        $weak = AnswerFitness::where('project_id', $project->id)->where('field_key', 'success_metric')->first();
        $strong = AnswerFitness::where('project_id', $project->id)->where('field_key', 'what_works_now')->first();

        $this->assertNotNull($weak);
        $this->assertSame(AnswerFitness::VERDICT_INSUFFICIENT, $weak->verdict);
        $this->assertSame(AnswerFitness::VERDICT_SUFFICIENT, $strong->verdict);
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function owned(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاطي']);
        $project->brainFacts()->delete();
        AnswerFitness::where('project_id', $project->id)->delete();
        $project->workspace->forceFill(['monthly_query_limit' => 50])->save();

        return [$user, $project->fresh()];
    }
}
