<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCompetitor;
use App\Models\User;
use App\Services\Competitors\CompetitorRegistry;
use App\Services\Projects\ProjectService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * حلقة «نقترح ← يؤكد» من داخل التقرير: المستخدم يؤكّد مرشّحًا، يستبعد آخر،
 * أو يضيف منافسًا محليًا — والتغيير يظهر فورًا في تقريره الحيّ.
 */
class CompetitorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    #[Test]
    public function the_owner_confirms_a_candidate_into_a_real_competitor(): void
    {
        [$user, $project] = $this->ownerAndProject();
        app(CompetitorRegistry::class)->suggest($project, [['name' => 'علامة إقليمية']]);
        $candidate = $project->competitors()->firstOrFail();

        $this->actingAs($user)
            ->post(route('app.competitors.confirm', $candidate))
            ->assertRedirect();

        $this->assertSame(ProjectCompetitor::STATUS_CONFIRMED, $candidate->fresh()->status);
    }

    #[Test]
    public function the_owner_dismisses_an_irrelevant_candidate(): void
    {
        [$user, $project] = $this->ownerAndProject();
        app(CompetitorRegistry::class)->suggest($project, [['name' => 'ليس منافسًا']]);
        $candidate = $project->competitors()->firstOrFail();

        $this->actingAs($user)
            ->post(route('app.competitors.dismiss', $candidate))
            ->assertRedirect();

        $this->assertSame(ProjectCompetitor::STATUS_DISMISSED, $candidate->fresh()->status);
    }

    #[Test]
    public function the_owner_adds_local_competitors_by_name(): void
    {
        [$user, $project] = $this->ownerAndProject();

        $this->actingAs($user)
            ->post(route('app.competitors.store', $project->slug), ['names' => 'عسل الحاج، @honey_sd'])
            ->assertRedirect();

        $this->assertSame(2, $project->competitors()->count());
        $this->assertTrue($project->competitors()->local()->confirmed()->exists());
    }

    #[Test]
    public function a_stranger_cannot_touch_another_owners_competitor(): void
    {
        [, $project] = $this->ownerAndProject();
        app(CompetitorRegistry::class)->suggest($project, [['name' => 'مرشّح']]);
        $candidate = $project->competitors()->firstOrFail();

        $intruder = User::factory()->create();

        // 404 لا 403: لا نؤكد وجود المورد لمن لا يملكه.
        $this->actingAs($intruder)
            ->post(route('app.competitors.dismiss', $candidate))
            ->assertNotFound();

        $this->assertSame(ProjectCompetitor::STATUS_CANDIDATE, $candidate->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function ownerAndProject(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع']);

        return [$user, $project];
    }
}
