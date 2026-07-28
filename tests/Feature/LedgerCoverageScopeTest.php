<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolField;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Reporting\AgencyStateLedger;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * التغطية تُقاس على ما عُرض على صاحب المشروع، لا على الكتالوج كله.
 *
 * قبل الإصلاح: المقام كان كل مفتاح إلزامي في الأدوات الإحدى عشرة (117)،
 * بينما يشترط موجز الوكالة ثلاث أدوات فيها 39 منها — فمن أنجز ما طُلب منه
 * بالضبط كان يقرأ «تغطيتك 33٪ أو أقل» ولا يستطيع تجاوزها مهما فعل.
 */
class LedgerCoverageScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function a_project_that_ran_nothing_is_not_charged_with_the_whole_catalogue(): void
    {
        $ledger = app(AgencyStateLedger::class)->build($this->project()[1]);

        // لا سؤال عُرض بعد ⇒ لا نقص يُنسب إلى صاحب المشروع.
        $this->assertSame(0, $ledger['coverage']['unanswered']);
        $this->assertGreaterThan(0, $ledger['coverage']['answered']);
        $this->assertSame(100, $ledger['coverage']['percent']);

        // وما لم يُغطَّ يظهر كأدوات باسمها، لا كأسئلة معلّقة.
        $this->assertNotEmpty($ledger['not_covered']);
        $this->assertArrayHasKey('tool', $ledger['not_covered'][0]);
        $this->assertArrayHasKey('adds', $ledger['not_covered'][0]);
    }

    #[Test]
    public function completing_a_tool_makes_its_questions_countable_and_reaches_full_coverage(): void
    {
        [$user, $project] = $this->project();

        $this->completeTool($project, $user, 'marketing-score');

        $ledger = app(AgencyStateLedger::class)->build($project->fresh());

        // كل ما عُرض أُجيب ⇒ 100٪، لا سقف بنيوي من أدوات لم تُفتح.
        $this->assertSame(0, $ledger['coverage']['unanswered']);
        $this->assertSame(100, $ledger['coverage']['percent']);
        $this->assertStringContainsString(
            Tool::where('key', 'marketing-score')->value('title'),
            $ledger['coverage']['basis'],
        );

        // الأداة المكتملة لم تعد ضمن «لم يُغطَّ».
        $this->assertNotContains(
            'marketing-score',
            collect($ledger['not_covered'])->pluck('tool_key')->all(),
        );
    }

    #[Test]
    public function a_question_that_was_shown_and_skipped_is_still_reported_as_missing(): void
    {
        [$user, $project] = $this->project();

        // تشغيل غادر المسودة دون إجابات: الأسئلة عُرضت ولم تُجب.
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $project->runs()->create([
            'tool_version_id' => $tool->current_version_id,
            'user_id' => $user->id,
            'status' => ToolRun::STATUS_COMPLETED,
            'base_score' => 50,
        ]);

        $ledger = app(AgencyStateLedger::class)->build($project->fresh());

        // هذا نقص حقيقي ويجب أن يظهر — التمييز ليس تخفيفًا للجميع.
        $this->assertGreaterThan(0, $ledger['coverage']['unanswered']);
        $this->assertLessThan(100, $ledger['coverage']['percent']);
    }

    #[Test]
    public function questions_that_do_not_apply_to_this_project_are_never_counted(): void
    {
        [$user, $project] = $this->project();

        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        // سؤال إلزامي لا يظهر إلا لنوع مشروع آخر.
        ToolField::create([
            'tool_version_id' => $tool->current_version_id,
            'key' => 'ecommerce_only_probe',
            'label' => 'سؤال يخص المتاجر وحدها',
            'type' => 'text',
            'required' => true,
            'step' => 1,
            'step_title' => 'خطوة',
            'sort_order' => 99,
            'visible_when' => ['project.type' => 'never-matching-value'],
        ]);

        $this->completeTool($project, $user, 'marketing-score');

        $ledger = app(AgencyStateLedger::class)->build($project->fresh());

        $missingKeys = collect($ledger['themes'])
            ->flatMap(fn (array $theme) => collect($theme['unanswered'])->pluck('key'))
            ->all();

        $this->assertNotContains('ecommerce_only_probe', $missingKeys);
    }

    /**
     * إكمال أداة بملء كل حقولها الظاهرة والإلزامية عبر مسار الحفظ الحقيقي.
     */
    private function completeTool(Project $project, User $user, string $key): void
    {
        $tool = Tool::where('key', $key)->firstOrFail();
        $service = app(ToolRunService::class);
        $run = $service->start($project, $tool, $user);

        $steps = $run->toolVersion->fields
            ->filter(fn (ToolField $field) => $field->required)
            ->groupBy('step');

        foreach ($steps as $step => $fields) {
            $input = [];

            foreach ($fields as $field) {
                $input[$field->key] = $this->sampleValue($field);
            }

            $service->saveStep($run, (int) $step, $input);
        }

        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED])->save();
    }

    private function sampleValue(ToolField $field): mixed
    {
        $options = collect($field->options ?? [])->pluck('value')->filter()->values();

        return match ($field->type) {
            'number' => 1000,
            'select', 'radio' => $options->first() ?? 'قيمة',
            'multiselect', 'checkbox' => $options->take(1)->all() ?: ['قيمة'],
            'textarea' => str_repeat('نص واضح ومفصل بما يكفي. ', 4),
            default => 'قيمة اختبار واضحة',
        };
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع القياس',
            'industry' => 'خدمات',
            'description' => 'خدمة للشركات الصغيرة.',
            'geography' => 'الرياض',
        ]);

        return [$user, $project];
    }
}
