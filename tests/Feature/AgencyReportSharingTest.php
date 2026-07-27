<?php

namespace Tests\Feature;

use App\Models\AgencyReport;
use App\Models\Finding;
use App\Models\Project;
use App\Models\Recommendation;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Reports\AgencyReportPdfGenerator;
use App\Services\Reports\AgencyReportService;
use App\Services\Reports\AgencyReportSharing;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgencyReportSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function a_shared_link_opens_without_login_and_every_view_is_recorded(): void
    {
        [$user, $report] = $this->report();

        $this->actingAs($user)
            ->post(route('app.agency-reports.share', $report), ['days' => 30])
            ->assertRedirect();

        $token = $report->fresh()->share_token;
        $this->assertNotNull($token);

        // زائر بلا حساب — هذا هو الغرض من الرابط.
        $this->get(route('shared.agency-report', $token))
            ->assertOk()
            ->assertSee('موجز تكليف مشترك', false);

        $this->assertSame(1, $report->fresh()->views()->count());

        $status = app(AgencyReportSharing::class)->status($report->fresh());
        $this->assertTrue($status['is_live']);
        $this->assertSame(1, $status['views_count']);
    }

    #[Test]
    public function revoking_closes_the_link_immediately_and_it_cannot_be_reused(): void
    {
        [$user, $report] = $this->report();
        app(AgencyReportSharing::class)->share($report, 30);
        $token = $report->fresh()->share_token;

        $this->actingAs($user)
            ->delete(route('app.agency-reports.share.revoke', $report))
            ->assertRedirect();

        $this->get(route('shared.agency-report', $token))->assertNotFound();
        $this->get(route('shared.agency-report.pdf', $token))->assertNotFound();

        $fresh = $report->fresh();
        $this->assertNull($fresh->share_token);
        $this->assertNotNull($fresh->share_revoked_at);
    }

    #[Test]
    public function an_expired_link_stops_working_on_its_own(): void
    {
        [, $report] = $this->report();
        app(AgencyReportSharing::class)->share($report, 7);
        $token = $report->fresh()->share_token;

        $report->forceFill(['share_expires_at' => now()->subMinute()])->save();

        $this->get(route('shared.agency-report', $token))->assertNotFound();
        $this->assertFalse(app(AgencyReportSharing::class)->isLive($report->fresh()));
    }

    #[Test]
    public function the_public_api_returns_the_shared_data_without_the_owner_guide(): void
    {
        [, $report] = $this->report();
        app(AgencyReportSharing::class)->share($report, 30);
        $token = $report->fresh()->share_token;

        $this->getJson(route('api.v1.public.shared-reports.show', $token))
            ->assertOk()
            ->assertJsonPath('data.document.title', 'موجز التكليف — مشروع المشاركة')
            ->assertJsonMissingPath('data.snapshot.owner_guide');

        $this->assertDatabaseHas('agency_report_views', [
            'agency_report_id' => $report->id,
            'channel' => 'api',
        ]);

        $report->forceFill(['share_expires_at' => now()->subMinute()])->save();

        $this->getJson(route('api.v1.public.shared-reports.show', $token))->assertNotFound();
        $this->getJson(route('api.v1.public.shared-reports.show', 'unknown-token'))->assertNotFound();
    }

    #[Test]
    public function a_stranger_cannot_share_or_revoke_someone_elses_report(): void
    {
        [, $report] = $this->report();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('app.agency-reports.share', $report))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->delete(route('app.agency-reports.share.revoke', $report))
            ->assertNotFound();

        $this->assertNull($report->fresh()->share_token);
    }

    #[Test]
    public function regenerating_the_pdf_removes_the_previous_file_instead_of_leaving_it_behind(): void
    {
        Storage::fake('local');
        [, $report] = $this->report();

        $stale = 'agency-reports/agency-report-'.$report->id.'-v0.pdf';
        Storage::disk('local')->put($stale, 'نسخة قديمة');
        $report->forceFill(['pdf_path' => $stale])->save();

        $fresh = app(AgencyReportPdfGenerator::class)->ensure($report->fresh());

        $this->assertNotSame($stale, $fresh);
        Storage::disk('local')->assertExists($fresh);
        Storage::disk('local')->assertMissing($stale);
    }

    /**
     * @return array{0: User, 1: AgencyReport}
     */
    private function report(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع المشاركة']);
        $project->profile()->updateOrCreate([], ['monthly_budget' => 8000]);
        app(AgencyReportService::class)->saveBrief($project, [
            'services' => ['ads'],
            'primary_goal' => 'sales',
            'success_metric' => '20 عملية شراء مدفوعة خلال 90 يومًا.',
            'budget_includes_agency_fee' => 'yes',
            'budget_currency' => 'SAR',
            'account_ownership' => 'mine',
            'proposal_deadline' => '15 أغسطس 2026',
        ]);

        foreach (['marketing-score' => 60, 'brand-clarity' => 66, 'audience-map' => 57] as $key => $score) {
            $this->reportFor($project, $user, $key, $score);
        }

        return [$user, app(AgencyReportService::class)->generate($project->fresh(), $user)];
    }

    private function reportFor(Project $project, User $user, string $toolKey, int $score): void
    {
        $tool = Tool::where('key', $toolKey)->firstOrFail();
        $run = $project->runs()->create([
            'tool_version_id' => $tool->current_version_id,
            'user_id' => $user->id,
            'status' => ToolRun::STATUS_COMPLETED,
            'base_score' => $score,
        ]);

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => "تقرير {$tool->title}",
            'status' => 'published',
            'score' => $score,
            'score_band' => Report::bandFor($score),
            'summary' => "ملخص {$tool->title}.",
            'assumptions' => [],
        ]);

        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => "نتيجة {$toolKey}",
            'description' => 'وصف النتيجة.',
            'severity' => 'medium',
            'evidence' => 'إجابة موثقة.',
            'confidence' => 75,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);

        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'title' => "أولوية {$toolKey}",
            'description' => 'خطوة تنفيذية.',
            'impact' => 'medium',
            'effort' => 'low',
            'priority' => 70,
        ]);
    }
}
