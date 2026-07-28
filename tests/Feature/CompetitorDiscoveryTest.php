<?php

namespace Tests\Feature;

use App\Contracts\CompetitorProvider;
use App\Models\Project;
use App\Models\ProjectCompetitor;
use App\Models\User;
use App\Modules\Competitors\CompetitorDiscovery;
use App\Services\Projects\ProjectService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الاكتشاف الإقليمي: ما نكتشفه مرشّحات لا حقائق، ولا يُفعّل بلا مصدر —
 * لا اختلاق أسماء حين يغيب المصدر الحيّ.
 */
class CompetitorDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    #[Test]
    public function discovered_domains_become_regional_candidates_awaiting_confirmation(): void
    {
        $this->fakeProvider([
            ['name' => 'honeyrivals.com', 'url' => 'https://honeyrivals.com', 'tier' => ProjectCompetitor::TIER_REGIONAL],
            ['name' => 'gulfhoney.sa', 'url' => 'https://gulfhoney.sa', 'tier' => ProjectCompetitor::TIER_REGIONAL],
        ]);

        $project = $this->project();
        $count = app(CompetitorDiscovery::class)->discoverFor($project);

        $this->assertSame(2, $count);

        $candidate = $project->competitors()->firstOrFail();
        // مرشّح لا حقيقة: بانتظار التأكيد، مصدره النظام.
        $this->assertSame(ProjectCompetitor::STATUS_CANDIDATE, $candidate->status);
        $this->assertSame(ProjectCompetitor::SOURCE_SUGGESTED, $candidate->source);
        $this->assertSame(ProjectCompetitor::TIER_REGIONAL, $candidate->tier);
    }

    #[Test]
    public function no_source_means_no_candidates_never_invented_ones(): void
    {
        $this->fakeProvider([], available: false);

        $project = $this->project();

        $this->assertFalse(app(CompetitorDiscovery::class)->isAvailable());
        $this->assertSame(0, app(CompetitorDiscovery::class)->discoverFor($project));
        $this->assertSame(0, $project->competitors()->count());
    }

    #[Test]
    public function discovery_never_overrides_a_competitor_the_user_named(): void
    {
        $this->fakeProvider([
            ['name' => 'honeyrivals.com', 'tier' => ProjectCompetitor::TIER_REGIONAL],
        ]);

        $project = $this->project();
        // المستخدم سمّى نفس الاسم محليًا.
        $project->competitors()->create([
            'name' => 'honeyrivals.com',
            'source' => ProjectCompetitor::SOURCE_NAMED,
            'tier' => ProjectCompetitor::TIER_LOCAL,
            'status' => ProjectCompetitor::STATUS_CONFIRMED,
        ]);

        app(CompetitorDiscovery::class)->discoverFor($project);

        $row = $project->competitors()->firstOrFail();
        $this->assertSame(ProjectCompetitor::STATUS_CONFIRMED, $row->status);
        $this->assertSame(ProjectCompetitor::TIER_LOCAL, $row->tier);
        $this->assertSame(1, $project->competitors()->count());
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function fakeProvider(array $candidates, bool $available = true): void
    {
        $this->app->bind(CompetitorProvider::class, fn () => new class($candidates, $available) implements CompetitorProvider
        {
            public function __construct(private array $candidates, private bool $available) {}

            public function discover(Project $project): array
            {
                return $this->available ? $this->candidates : [];
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }
        });
    }

    private function project(): Project
    {
        $user = User::factory()->create();

        return app(ProjectService::class)->create($user, ['name' => 'متجر عسل']);
    }
}
