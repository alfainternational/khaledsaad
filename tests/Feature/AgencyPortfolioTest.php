<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Reporting\AgencyPortfolio;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Billing\Entitlements;
use App\Services\Projects\ProjectService;
use App\Support\Billing\FeatureKey;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * لوحة الوكالة: جدول القرار الصباحي.
 *
 * ما يُحرَس هنا هو ترتيب الجدول ودلالة خلاياه. جدول محفظة يعرض «صفر» لعميل
 * لم يُقَس يقرأه صاحب الوكالة حكمًا على عمله هو، ويدفعه لقرار خاطئ على عميل
 * لم يبدأ قياسه أصلًا.
 */
class AgencyPortfolioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_unmeasured_business_is_named_not_scored_zero(): void
    {
        $user = $this->agencyUser();
        $this->projectFor($user, 'عميل بلا قياس');

        $portfolio = app(AgencyPortfolio::class)->for($user->primaryWorkspace());
        $row = $portfolio['projects'][0];

        $this->assertFalse($row['measured']);
        $this->assertNull($row[MetricKey::MATURITY_SCORE]);

        // ولا يدخل المتوسط: متوسط يشمل غير المقيس كذبٌ بالحساب.
        $this->assertNull($portfolio['summary']['average_score']);
        $this->assertSame(1, $portfolio['summary']['unmeasured']);
    }

    #[Test]
    public function the_neediest_business_comes_first(): void
    {
        $user = $this->agencyUser();

        $this->measuredProject($user, 'عميل قوي', axes: 2);
        $this->measuredProject($user, 'عميل ضعيف', axes: 1);
        $this->projectFor($user, 'عميل بلا قياس');

        $names = array_column(array_column(
            app(AgencyPortfolio::class)->for($user->primaryWorkspace())['projects'],
            'project',
        ), 'name');

        // غير المقيس أولًا: هو أول ما يجب أن تفعله الوكالة، وإخفاؤه في الذيل
        // يجعله يُنسى شهورًا.
        $this->assertSame('عميل بلا قياس', $names[0]);
        $this->assertSame('عميل ضعيف', $names[1]);
        $this->assertSame('عميل قوي', $names[2]);
    }

    #[Test]
    public function a_wider_measurement_is_not_reported_as_a_trend(): void
    {
        $user = $this->agencyUser();
        $project = $this->measuredProject($user, 'عميل', axes: 1);

        // قياسان: الثاني بمحاور أكثر. الفرق اتساع قياس لا تغيّر نشاط.
        $this->scoreEvent($project, 40, axesActive: 1);
        $this->scoreEvent($project, 70, axesActive: 2);

        $row = app(AgencyPortfolio::class)->for($user->primaryWorkspace())['projects'][0];

        $this->assertSame('unknown', $row['trend']['direction']);
        $this->assertNotNull($row['trend']['reason']);
    }

    #[Test]
    public function a_real_decline_is_counted_in_the_summary(): void
    {
        $user = $this->agencyUser();
        $project = $this->measuredProject($user, 'عميل يتراجع', axes: 1);

        $this->scoreEvent($project, 70, axesActive: 1);
        $this->scoreEvent($project, 55, axesActive: 1);

        $portfolio = app(AgencyPortfolio::class)->for($user->primaryWorkspace());

        $this->assertSame('down', $portfolio['projects'][0]['trend']['direction']);
        $this->assertSame(-15, $portfolio['projects'][0]['trend']['delta']);
        $this->assertSame(1, $portfolio['summary']['declining']);
    }

    #[Test]
    public function the_board_renders_for_an_entitled_workspace(): void
    {
        $user = $this->agencyUser();
        $this->measuredProject($user, 'عميل', axes: 1);

        $this->actingAs($user)
            ->get(route('app.portfolio'))
            ->assertOk()
            ->assertSee('محفظة الأنشطة')
            ->assertSee('عميل');
    }

    #[Test]
    public function the_mobile_api_exposes_the_same_portfolio_as_the_web_service(): void
    {
        $user = $this->agencyUser();
        $this->measuredProject($user, 'عميل مقيس', axes: 1);
        $this->projectFor($user, 'عميل بلا قياس');
        $expected = app(AgencyPortfolio::class)->for($user->primaryWorkspace());

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.portfolio'))
            ->assertOk()
            ->assertExactJson(['data' => $expected]);
    }

    private function measuredProject(User $user, string $name, int $axes): Project
    {
        $project = $this->projectFor($user, $name);
        $brain = app(BrainWriter::class);

        $brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');

        if ($axes > 1) {
            $brain->record($project, 'owned_contacts', 900, EvidenceLevel::Measured, 'OwnedAssets');
        }

        return $project->fresh();
    }

    private function projectFor(User $user, string $name): Project
    {
        $project = app(ProjectService::class)->create($user, ['name' => $name]);
        $project->brainFacts()->delete();

        return $project->fresh();
    }

    private function scoreEvent(Project $project, int $score, int $axesActive): void
    {
        app(BrainWriter::class)->event($project, BrainEvent::TYPE_DIAGNOSIS_SCORED, [
            MetricKey::MATURITY_SCORE => $score,
            'score_coverage' => 1.0,
            'axes_active' => $axesActive,
        ]);
    }

    /**
     * مستخدم على خطة تشمل تقارير الوكالة، وبحد مشاريع يسمح بمحفظة.
     */
    private function agencyUser(): User
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);

        foreach ([FeatureKey::REPORTS_AGENCY => null, FeatureKey::PROJECTS_LIMIT => 10] as $key => $value) {
            PlanFeature::updateOrCreate(
                [
                    'plan_id' => Plan::where('key', 'free')->value('id'),
                    'feature_id' => Feature::where('key', $key)->value('id'),
                ],
                ['enabled' => true, 'value' => $value],
            );
        }

        app(Entitlements::class)->flush();

        return User::factory()->create();
    }
}
