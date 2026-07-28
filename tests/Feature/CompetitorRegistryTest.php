<?php

namespace Tests\Feature;

use App\Models\ProjectCompetitor;
use App\Models\User;
use App\Modules\Competitors\CompetitorRegistry;
use App\Services\Projects\ProjectService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * سجلّ المنافسين: المحلي يقين يسمّيه المستخدم، والإقليمي مرشّح نقترحه،
 * والمحلي يقود الترتيب لأنه مصدر أغلب الأثر.
 */
class CompetitorRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    #[Test]
    public function it_parses_free_text_into_confirmed_local_competitors(): void
    {
        $project = $this->project();

        app(CompetitorRegistry::class)->rememberNamed($project, "عسل الحاج، @honey_sd\nمناحل النيل");

        $competitors = $project->competitors()->get();

        $this->assertCount(3, $competitors);
        foreach ($competitors as $competitor) {
            $this->assertSame(ProjectCompetitor::SOURCE_NAMED, $competitor->source);
            $this->assertSame(ProjectCompetitor::TIER_LOCAL, $competitor->tier);
            $this->assertSame(ProjectCompetitor::STATUS_CONFIRMED, $competitor->status);
        }

        // معرّف إنستغرام يتحول إلى رابط لمتابعته.
        $handle = $competitors->firstWhere('name', '@honey_sd');
        $this->assertSame('https://instagram.com/honey_sd', $handle->url);
    }

    #[Test]
    public function a_suggested_candidate_never_overrides_a_named_competitor(): void
    {
        $project = $this->project();
        $registry = app(CompetitorRegistry::class);

        $registry->rememberNamed($project, 'عسل الحاج');
        $registry->suggest($project, [['name' => 'عسل الحاج', 'tier' => ProjectCompetitor::TIER_REGIONAL]]);

        $row = $project->competitors()->where('name', 'عسل الحاج')->firstOrFail();

        // يبقى كما سمّاه المستخدم: محلي مؤكد، لا مرشّح إقليمي.
        $this->assertSame(ProjectCompetitor::SOURCE_NAMED, $row->source);
        $this->assertSame(ProjectCompetitor::STATUS_CONFIRMED, $row->status);
        $this->assertSame(1, $project->competitors()->count());
    }

    #[Test]
    public function the_report_view_puts_local_first_and_separates_candidates(): void
    {
        $project = $this->project();
        $registry = app(CompetitorRegistry::class);

        $registry->suggest($project, [['name' => 'علامة إقليمية', 'tier' => ProjectCompetitor::TIER_REGIONAL]]);
        $registry->rememberNamed($project, 'منافس محلي');

        $view = $registry->forReport($project);

        $this->assertTrue($view['has_local']);
        $this->assertSame('منافس محلي', $view['confirmed'][0]['name']);
        $this->assertCount(1, $view['candidates']);
        $this->assertSame('علامة إقليمية', $view['candidates'][0]['name']);
    }

    #[Test]
    public function a_dismissed_candidate_drops_out_of_the_report(): void
    {
        $project = $this->project();
        $registry = app(CompetitorRegistry::class);

        $registry->suggest($project, [['name' => 'مرشّح غير ذي صلة']]);
        $registry->dismiss($project->competitors()->firstOrFail());

        $view = $registry->forReport($project);

        $this->assertSame([], $view['candidates']);
        $this->assertSame([], $view['confirmed']);
    }

    private function project()
    {
        $user = User::factory()->create();

        return app(ProjectService::class)->create($user, ['name' => 'مشروع تجريبي']);
    }
}
