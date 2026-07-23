<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\Tool;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\RunPresenter;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * قاعدة واحدة يحرسها هذا الملف: ما كتبه صاحب المشروع مرة واحدة
 * لا يُطلب منه مرة أخرى، ولا يضيع داخل أداة واحدة.
 */
class ProjectAnswerMemoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);

        $this->user = User::factory()->create();
        $this->project = app(ProjectService::class)->create($this->user, ['name' => 'متجر تجريبي']);
    }

    #[Test]
    public function what_the_owner_writes_inside_a_tool_appears_in_the_project_file(): void
    {
        $run = $this->startRun('marketing-score');

        app(ToolRunService::class)->saveStep($run, 1, [
            'business_model' => 'services',
            'description' => 'خدمة استشارات تسويقية للمتاجر الصغيرة داخل المدينة.',
            'geography' => 'الرياض',
            'monthly_budget' => 1500,
        ]);

        // الملف الذي يراه المستخدم في صفحة مشروعه لم يعد فارغًا.
        $profile = $this->project->fresh()->profile;

        $this->assertSame('services', $profile->business_model);
        $this->assertStringContainsString('استشارات', $profile->description);
        $this->assertSame('الرياض', $profile->geography);
        $this->assertSame(1500, (int) $profile->monthly_budget);
    }

    #[Test]
    public function a_second_tool_does_not_ask_again_for_what_was_already_answered(): void
    {
        $first = $this->startRun('marketing-score');

        app(ToolRunService::class)->saveStep($first, 2, [
            'primary_goal' => 'sales',
            'value_proposition' => 'أوصّل في نفس اليوم بينما غيري يحتاج ثلاثة أيام كاملة.',
            'audience_clarity' => 'rough',
        ]);

        $second = $this->startRun('content-engine');
        $wizard = app(RunPresenter::class)->wizard($second);

        $fields = collect($wizard['steps'])->flatMap(fn (array $step) => $step['fields'])->keyBy('key');

        // السؤال نفسه في أداة أخرى: مملوء ومعلَّم كمعروف بدل أن يُطلب من جديد.
        $this->assertTrue($fields['value_proposition']['is_known']);
        $this->assertStringContainsString('نفس اليوم', $fields['value_proposition']['value']);

        // وما لم يُجب عنه بعد يبقى سؤالًا جديدًا.
        $this->assertFalse($fields['content_goal']['is_known']);
    }

    #[Test]
    public function editing_the_project_file_updates_what_the_tools_know(): void
    {
        app(ProjectService::class)->updateProfile($this->project, [
            'value_proposition' => 'أضمن استرجاع المبلغ خلال أسبوع دون أسئلة.',
        ]);

        $stored = ProjectAnswer::where('project_id', $this->project->id)
            ->where('field_key', 'value_proposition')
            ->firstOrFail();

        $this->assertStringContainsString('استرجاع المبلغ', $stored->value());
    }

    #[Test]
    public function questions_that_need_a_market_number_carry_one_for_comparison(): void
    {
        $run = $this->startRun('marketing-score');
        $wizard = app(RunPresenter::class)->wizard($run);

        $fields = collect($wizard['steps'])->flatMap(fn (array $step) => $step['fields'])->keyBy('key');

        // خانة فارغة أمام سؤال عن رقم لا يعرفه المستخدم: نعطيه مدى للمقارنة.
        $this->assertNotNull($fields['known_cac']['benchmark']);
        $this->assertNotEmpty($fields['known_cac']['benchmark']['text']);
        $this->assertStringContainsString('تقدير', $fields['known_cac']['benchmark']['source']);
    }

    private function startRun(string $toolKey)
    {
        $tool = Tool::where('key', $toolKey)->firstOrFail();

        return app(ToolRunService::class)->start($this->project, $tool, $this->user);
    }
}
