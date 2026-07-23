<?php

namespace Tests\Feature;

use App\Models\Tool;
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
        // الخطوة 3 لمشروع الفكرة سؤال واحد فقط: المحتوى (يخص الجميع).
        app(ToolRunService::class)->saveStep($run, 3, ['content_cadence' => 'weekly']);

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

        $this->assertSame('ecommerce', $context['project.business_model']);
        $this->assertSame('operating', $context['project.maturity']);
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
