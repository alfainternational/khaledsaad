<?php

namespace Tests\Feature;

use App\Contracts\AdLibraryProvider;
use App\Models\CompetitorAdSnapshot;
use App\Models\Project;
use App\Models\ProjectCompetitor;
use App\Models\User;
use App\Modules\Competitors\AdLibraries\AdLibraryScan;
use App\Modules\Competitors\AdLibraries\AdSnapshot;
use App\Modules\Competitors\AdLibraries\UnavailableAdLibraryProvider;
use App\Modules\Measurement\QueryBudgetManager;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * سحب مكتبات الإعلانات بحدّ الأمانة (المرحلة ٤، سياسة المصادر §١٠).
 *
 * القاعدة التي يحرسها هذا الملف قبل أي رقم: **انكسار السحب يُعلَن فقدانَ
 * تغطية لا «لا إعلانات لديه»** (§٤.٣). المزوّد الافتراضي لا يختلق، والحجز
 * يسبق الاستدعاء (§٩)، وكل لقطة تحمل مصدرها وتاريخها.
 */
class AdLibraryScanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_default_provider_declares_the_gap_and_invents_nothing(): void
    {
        $project = $this->projectWithCompetitor();

        // المزوّد الافتراضي (غير المضبوط) هو المربوط في الحاوية.
        $summary = app(AdLibraryScan::class)->forProject($project);

        $this->assertSame(0, $summary['fetched']);
        $this->assertGreaterThan(0, $summary['unavailable']);

        $snapshots = CompetitorAdSnapshot::all();
        $this->assertTrue($snapshots->isNotEmpty());

        foreach ($snapshots as $snapshot) {
            // لا إعلان مختلق، وحالة صريحة، وتاريخ رصد مع كل صف.
            $this->assertSame(AdSnapshot::UNAVAILABLE, $snapshot->status);
            $this->assertEmpty($snapshot->ads);
            $this->assertNotEmpty($snapshot->coverage_note);
            $this->assertNotNull($snapshot->captured_at);
        }
    }

    #[Test]
    public function a_fetched_snapshot_carries_its_ads_with_source_and_date(): void
    {
        $project = $this->projectWithCompetitor(budget: 50);

        $this->useProvider(new class implements AdLibraryProvider
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function supportedPlatforms(): array
            {
                return ['meta'];
            }

            public function fetch(string $platform, string $advertiser): AdSnapshot
            {
                return AdSnapshot::fetched(
                    $platform,
                    [['headline' => 'خصم رمضان', 'started' => '2026-03-01']],
                    'https://www.facebook.com/ads/library?q='.$advertiser,
                );
            }
        });

        app(AdLibraryScan::class)->forProject($project);

        $snapshot = CompetitorAdSnapshot::where('status', AdSnapshot::FETCHED)->firstOrFail();
        $this->assertCount(1, $snapshot->ads);
        $this->assertStringContainsString('facebook.com/ads/library', $snapshot->source_url);
        $this->assertNotNull($snapshot->captured_at);
    }

    #[Test]
    public function a_broken_scrape_is_declared_not_read_as_no_ads(): void
    {
        $project = $this->projectWithCompetitor(budget: 50);

        $this->useProvider(new class implements AdLibraryProvider
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function supportedPlatforms(): array
            {
                return ['meta'];
            }

            public function fetch(string $platform, string $advertiser): AdSnapshot
            {
                // الصفحة تغيّرت: المزوّد يرمي، والطبقة تترجمه تغطيةً لا صفرًا.
                throw new RuntimeException('selector changed');
            }
        });

        app(AdLibraryScan::class)->forProject($project);

        $snapshot = CompetitorAdSnapshot::firstOrFail();
        $this->assertSame(AdSnapshot::BROKE, $snapshot->status);
        $this->assertEmpty($snapshot->ads);
        $this->assertNotEmpty($snapshot->coverage_note);
    }

    #[Test]
    public function a_broken_scrape_returns_the_reserved_place_to_the_budget(): void
    {
        $project = $this->projectWithCompetitor(budget: 3);

        $this->useProvider(new class implements AdLibraryProvider
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function supportedPlatforms(): array
            {
                return ['meta'];
            }

            public function fetch(string $platform, string $advertiser): AdSnapshot
            {
                throw new RuntimeException('down');
            }
        });

        app(AdLibraryScan::class)->forProject($project);

        // عطل السحب لا يُحاسَب عليه: الموضع المحجوز عاد كاملًا.
        $budget = app(QueryBudgetManager::class)->budgetFor($project->workspace)->fresh();
        $this->assertSame(0, $budget->committed());
    }

    #[Test]
    public function an_exhausted_budget_stops_the_scan_without_inventing(): void
    {
        $project = $this->projectWithCompetitor(budget: 0);

        $this->useProvider(new class implements AdLibraryProvider
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function supportedPlatforms(): array
            {
                return ['meta'];
            }

            public function fetch(string $platform, string $advertiser): AdSnapshot
            {
                throw new RuntimeException('should never be called past the cap');
            }
        });

        app(AdLibraryScan::class)->forProject($project);

        $snapshot = CompetitorAdSnapshot::firstOrFail();
        // نفاد السقف تغطية غائبة معلنة لا `broke`، والمزوّد لم يُستدعَ.
        $this->assertSame(AdSnapshot::UNAVAILABLE, $snapshot->status);
    }

    #[Test]
    public function the_command_is_honest_when_no_provider_is_configured(): void
    {
        // الافتراضي غير مضبوط: الأمر لا يكتب لقطات وهمية.
        $this->assertInstanceOf(
            UnavailableAdLibraryProvider::class,
            app(AdLibraryProvider::class),
        );

        $this->artisan('competitors:scan-ads')
            ->expectsOutputToContain('لم يُفعَّل مزوّد سحب مكتبات الإعلانات')
            ->assertSuccessful();

        $this->assertSame(0, CompetitorAdSnapshot::count());
    }

    private function useProvider(AdLibraryProvider $provider): void
    {
        $this->app->instance(AdLibraryProvider::class, $provider);
    }

    private function projectWithCompetitor(int $budget = 10): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاطي']);
        $project->workspace->forceFill(['monthly_query_limit' => $budget])->save();

        ProjectCompetitor::create([
            'project_id' => $project->id,
            'name' => 'منافس مؤكَّد',
            'url' => 'https://rival.test',
            'tier' => 'local',
            'status' => ProjectCompetitor::STATUS_CONFIRMED,
            'source' => ProjectCompetitor::SOURCE_NAMED,
        ]);

        return $project->fresh();
    }
}
