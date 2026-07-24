<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolField;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\AnswerCompleteness;
use App\Services\Tools\ProjectContextResolver;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\RunPresenter;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * موجّه الأسئلة الذكي: الأسئلة تتبع نوع المشروع وحالته — كثيرة إن لزمت،
 * قليلة إن كفت — والدرجة تُحسب على المنطبق منها فقط.
 */
class AdaptiveQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function an_idea_stage_project_is_not_asked_operational_questions(): void
    {
        // مستخدمان لأن الخطة المجانية تسمح بمشروع واحد.
        $ideaOwner = User::factory()->create();
        $operatingOwner = User::factory()->create();
        $ideaProject = app(ProjectService::class)->create($ideaOwner, ['name' => 'فكرة مشروع', 'stage' => 'idea']);
        $operatingProject = app(ProjectService::class)->create($operatingOwner, ['name' => 'مشروع شغّال', 'stage' => 'growth']);

        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        $ideaRun = app(ToolRunService::class)->start($ideaProject, $tool, $ideaOwner);
        $operatingRun = app(ToolRunService::class)->start($operatingProject, $tool, $operatingOwner);

        $ideaKeys = $this->wizardFieldKeys($ideaRun);
        $operatingKeys = $this->wizardFieldKeys($operatingRun);

        // مشروع الفكرة لا يُسأل عن قنواته الشغالة ولا عمّا بعد البيع.
        foreach (['active_channels', 'tracking_maturity', 'retention_motion', 'known_cac'] as $operational) {
            $this->assertNotContains($operational, $ideaKeys, "سؤال {$operational} لا يخص مشروع فكرة.");
            $this->assertContains($operational, $operatingKeys, "سؤال {$operational} يخص مشروعًا يبيع.");
        }

        // الأسئلة التأسيسية تصل للجميع — لا إخلال بالجودة.
        foreach (['description', 'value_proposition', 'primary_goal'] as $core) {
            $this->assertContains($core, $ideaKeys);
            $this->assertContains($core, $operatingKeys);
        }

        $this->assertLessThan(count($operatingKeys), count($ideaKeys));
    }

    #[Test]
    public function the_score_is_computed_only_on_applicable_questions(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع فكرة', 'stage' => 'idea']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        // ملء الأسئلة التأسيسية المرئية فقط بإجابات قوية.
        app(ToolRunService::class)->saveStep($run, 1, [
            'business_model' => 'services',
            'description' => str_repeat('فكرة خدمة واضحة تحل مشكلة محددة ', 3),
            'geography' => 'الرياض',
            'monthly_budget' => 2000,
        ]);
        app(ToolRunService::class)->saveStep($run, 2, [
            'primary_goal' => 'leads',
            'value_proposition' => 'تسليم خلال 48 ساعة أو استرداد كامل',
            'audience_clarity' => 'documented',
        ]);
        app(ToolRunService::class)->saveStep($run, 3, [
            'content_cadence' => 'weekly',
            'presale_presence' => 'page',
        ]);
        app(ToolRunService::class)->saveStep($run, 4, [
            'validation_conversations' => 'several',
            'first_revenue_plan' => 'network',
        ]);

        $completeness = app(AnswerCompleteness::class);
        $run = $run->refresh()->loadMissing(['toolVersion.fields', 'answers']);

        // الاكتمال 100% رغم أن الأسئلة التشغيلية لم تُجب: لأنها غير موجهة أصلًا.
        $this->assertSame(100, $completeness->percent($run));
        $this->assertSame([], $completeness->missingRequired($run));

        // الحقول التشغيلية خارج المفاتيح النشطة فلا تسحب الدرجة للأسفل.
        $active = $completeness->visibleFields($run->toolVersion, $completeness->contextualAnswers($run))
            ->pluck('key')->all();
        $this->assertNotContains('tracking_maturity', $active);
    }

    #[Test]
    public function the_project_type_is_inferred_from_description_when_not_declared(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع غامض', 'stage' => 'growth']);
        $project->profile()->updateOrCreate([], [
            'description' => 'متجر إلكتروني لبيع منتجات العناية مع شحن لكل المدن.',
        ]);

        $context = app(ProjectContextResolver::class)->for($project->fresh());

        $this->assertSame('ecommerce', $context['project.sector']);
        $this->assertArrayNotHasKey('project.business_model', $context);
        $this->assertSame('operating', $context['project.maturity']);
    }

    #[Test]
    public function each_business_model_gets_the_question_that_decides_its_own_success(): void
    {
        // لكل نوع بيع سؤال يقيس الرقم الذي يحكم نجاحه، ولا يراه غيره.
        $expectations = [
            'b2b' => ['sees' => 'sales_cycle', 'hides' => ['trial_conversion', 'repeat_rate']],
            'saas' => ['sees' => 'trial_conversion', 'hides' => ['sales_cycle', 'repeat_rate']],
            'b2c' => ['sees' => 'repeat_rate', 'hides' => ['sales_cycle', 'trial_conversion']],
        ];

        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        foreach ($expectations as $model => $expected) {
            $owner = User::factory()->create();
            $project = app(ProjectService::class)->create($owner, [
                'name' => "مشروع {$model}",
                'stage' => 'growth',
            ]);
            $project->profile()->updateOrCreate([], ['business_model' => $model]);

            $run = app(ToolRunService::class)->start($project->fresh(), $tool, $owner);
            $keys = $this->wizardFieldKeys($run);

            $this->assertContains($expected['sees'], $keys, "نوع {$model} يجب أن يرى {$expected['sees']}.");

            foreach ($expected['hides'] as $hidden) {
                $this->assertNotContains($hidden, $keys, "نوع {$model} لا يجب أن يرى {$hidden}.");
            }
        }
    }

    #[Test]
    public function an_early_project_is_asked_its_own_pre_launch_questions(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'قبل الإطلاق', 'stage' => 'idea']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        $keys = $this->wizardFieldKeys(app(ToolRunService::class)->start($project, $tool, $user));

        // لا يُترك بلا قياس: يُسأل عمّا يحدد نجاحه في مرحلته هو.
        foreach (['validation_conversations', 'first_revenue_plan', 'presale_presence'] as $preLaunch) {
            $this->assertContains($preLaunch, $keys, "مشروع الفكرة يجب أن يُسأل {$preLaunch}.");
        }
    }

    #[Test]
    public function the_wizard_navigates_by_real_step_numbers_when_a_step_disappears(): void
    {
        // بوصلة البحث بلا موقع: خطوة كاملة تختفي، والتنقل يجب أن يتخطاها
        // بدل أن يقف أمام صفحة غير موجودة.
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع بلا موقع', 'stage' => 'growth']);
        $project->profile()->updateOrCreate([], ['business_model' => 'saas']);

        $tool = Tool::where('key', 'seo-compass')->firstOrFail();
        $run = app(ToolRunService::class)->start($project->fresh(), $tool, $user);

        $steps = app(RunPresenter::class)->wizard($run)['steps'];
        $numbers = array_column($steps, 'step');
        $positions = array_column($steps, 'position');

        // العرض متسلسل دائمًا (1..n) حتى لو كانت أرقام الخطوات غير متصلة.
        $this->assertSame(range(1, count($steps)), $positions);

        // طلب خطوة مخفية لا يكسر المعالج: يُحوَّل لأقرب خطوة قائمة.
        $hidden = array_values(array_diff([1, 2, 3], $numbers));

        if ($hidden !== []) {
            $this->actingAs($user)
                ->get(route('app.runs.step', [$run, $hidden[0]]))
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->get(route('app.runs.step', [$run, $numbers[0]]))
            ->assertOk();
    }

    #[Test]
    public function a_guest_run_without_a_project_still_sees_the_standard_question_set(): void
    {
        // الضيف بلا مشروع كان معرضًا لأن تُخفى عنه كل الأسئلة المشروطة.
        // السياق المحايد يضمن أن يرى المجموعة القياسية كاملة.
        $context = app(ProjectContextResolver::class)->for(null);

        $this->assertSame('operating', $context['project.maturity']);
        $this->assertArrayNotHasKey('project.business_model', $context, 'بلا تصريح لا نخمّن نوع البيع.');

        $field = new ToolField(['visible_when' => ['project.maturity' => 'operating']]);
        $this->assertTrue($field->isVisible($context));
    }

    #[Test]
    public function a_negated_condition_hides_only_the_excluded_types(): void
    {
        $field = new ToolField(['visible_when' => ['project.business_model' => ['!saas', '!marketplace']]]);

        $this->assertFalse($field->isVisible(['project.business_model' => 'saas']));
        $this->assertFalse($field->isVisible(['project.business_model' => 'marketplace']));
        $this->assertTrue($field->isVisible(['project.business_model' => 'b2c']));
        $this->assertTrue($field->isVisible([]), 'غياب القيمة لا يعني الاستثناء.');
    }

    /**
     * @return array<int, string>
     */
    private function wizardFieldKeys($run): array
    {
        $wizard = app(RunPresenter::class)->wizard($run->refresh());

        return collect($wizard['steps'])
            ->flatMap(fn (array $step) => array_column($step['fields'], 'key'))
            ->all();
    }
}
