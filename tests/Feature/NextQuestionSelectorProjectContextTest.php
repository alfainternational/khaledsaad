<?php

namespace Tests\Feature;

use App\Models\BlueprintModule;
use App\Models\ConsultationBlueprint;
use App\Models\ConsultationBlueprintVersion;
use App\Models\ConsultationSession;
use App\Models\DiagnosticModule;
use App\Models\ModuleQuestion;
use App\Models\Project;
use App\Models\QuestionDefinition;
use App\Models\QuestionVersion;
use App\Models\User;
use App\Modules\Intake\Engine\NextQuestionSelector;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * شروط project.* في مسار الاستشارة الموحّد تُقيَّم بنفس دلالات
 * ToolField::isVisible في مسار الأداة الكلاسيكي: سؤال القطاع يظهر
 * لمن ينطبق عليه وحده، لا لكل المشاريع بلا تمييز.
 */
class NextQuestionSelectorProjectContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_ecommerce_project_is_asked_the_checkout_steps_question(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر العناية', 'stage' => 'growth']);
        $project->profile()->updateOrCreate([], [
            'description' => 'متجر إلكتروني لبيع منتجات العناية مع شحن لكل المدن.',
        ]);

        $session = $this->sessionFor($user, $project->fresh());
        $next = app(NextQuestionSelector::class)->next($session);

        $this->assertNotNull($next);
        $this->assertSame('checkout_steps', $next->definition->internal_variable);
    }

    #[Test]
    public function a_services_project_never_sees_the_checkout_steps_question(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مكتب استشارات', 'stage' => 'growth']);
        $project->profile()->updateOrCreate([], [
            'description' => 'مكتب استشارات تسويقية للشركات الصغيرة.',
        ]);

        $selector = app(NextQuestionSelector::class);
        $session = $this->sessionFor($user, $project->fresh());

        // السؤال المشروط بالقطاع محجوب فيتقدّم السؤال العام رغم أولويته الأدنى.
        $first = $selector->next($session);
        $this->assertSame('primary_goal', $first?->definition->internal_variable);

        // وبعد الإجابة عن العام لا يبقى شيء: checkout_steps لا يظهر أبدًا.
        $session->answers()->create([
            'question_version_id' => $first->id,
            'value_json' => ['value' => 'زيادة العملاء'],
            'source' => 'user',
            'confidence' => 'medium',
        ]);
        $this->assertNull($selector->next($session->refresh()));
    }

    #[Test]
    public function a_project_without_a_profile_is_handled_safely(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع بلا ملف', 'stage' => 'growth']);

        $session = $this->sessionFor($user, $project);
        $next = app(NextQuestionSelector::class)->next($session);

        // بلا ملف تعريف لا يُخمَّن القطاع: يُحجب سؤال القطاع ويُقدَّم العام.
        $this->assertSame('primary_goal', $next?->definition->internal_variable);
    }

    /**
     * مخطط مصغّر بسؤالين: عام بلا شرط، وسؤال قطاع أعلى أولوية مشروط
     * بـ project.sector — كما ينسخه ConsultationCatalogBuilder من visible_when.
     */
    private function sessionFor(User $user, Project $project): ConsultationSession
    {
        $blueprint = ConsultationBlueprint::create(['key' => 'sector-test', 'name' => 'اختبار القطاع', 'status' => 'published']);
        $version = ConsultationBlueprintVersion::create([
            'consultation_blueprint_id' => $blueprint->id,
            'version' => 1,
            'status' => 'published',
            'settings' => ['depth_limits' => ['standard' => 35]],
        ]);
        $module = DiagnosticModule::create(['key' => 'funnel-test', 'name' => 'قمع الاختبار', 'sort_order' => 0]);
        $binding = BlueprintModule::create([
            'blueprint_version_id' => $version->id,
            'diagnostic_module_id' => $module->id,
            'importance' => 'core',
            'required' => true,
            'sort_order' => 0,
        ]);

        $this->bind($binding, 'FUNNEL.CHECKOUT_STEPS', 'checkout_steps', 5, ['project.sector' => 'ecommerce']);
        $this->bind($binding, 'FUNNEL.PRIMARY_GOAL', 'primary_goal', 2, null);

        $session = ConsultationSession::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'blueprint_version_id' => $version->id,
            'status' => ConsultationSession::STATUS_ACTIVE,
            'depth' => 'standard',
        ]);
        $session->moduleStates()->create(['diagnostic_module_id' => $module->id, 'state' => 'core']);

        return $session;
    }

    private function bind(BlueprintModule $module, string $key, string $variable, int $impact, ?array $showWhen): void
    {
        $definition = QuestionDefinition::create([
            'key' => $key,
            'internal_variable' => $variable,
            'sensitivity' => 'normal',
            'inferable' => false,
        ]);
        $question = QuestionVersion::create([
            'question_definition_id' => $definition->id,
            'version' => 1,
            'user_text' => 'سؤال '.$variable,
            'answer_type' => 'text',
            'options' => [],
            'required' => true,
            'allow_unknown' => true,
            'allow_skip' => false,
        ]);
        ModuleQuestion::create([
            'blueprint_module_id' => $module->id,
            'question_version_id' => $question->id,
            'diagnostic_impact' => $impact,
            'discrimination' => 3,
            'answer_burden' => 2,
            'critical' => true,
            'show_when' => $showWhen,
            'sort_order' => 0,
        ]);
    }
}
