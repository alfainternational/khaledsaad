<?php

namespace Tests\Feature\Projects;

use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Client\Models\Client;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Intelligence\Models\OfficialContact;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Jobs\RunProjectIntelligenceAuditJob;
use App\Models\User;
use App\Support\Intelligence\WebsiteAuditAnalyzer;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectIntelligenceAuditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_run_intelligence_audit_and_see_the_reported_outputs(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject();

        Http::fake([
            'https://example.com' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>ExampleCo B2B Services</title>
                        <meta name="description" content="حلول B2B عملية تساعد فرق المبيعات والتسويق على تنظيم الطلب والتحويل بسرعة.">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                    </head>
                    <body>
                        <h1>نظام نمو ومتابعة للشركات</h1>
                        <a href="/services">Services</a>
                        <a href="/contact">Contact</a>
                        <a href="/privacy">Privacy</a>
                        <a href="/terms">Terms</a>
                        <a href="mailto:info@example.com">info@example.com</a>
                        <a href="mailto:founder@example.com">founder@example.com</a>
                        <a href="https://instagram.com/exampleco">Instagram</a>
                        <a href="https://wa.me/966500000000">WhatsApp</a>
                        <form action="/contact"><input name="email"></form>
                        <p>Get started with a clear plan. Contact us for a quote today.</p>
                        <img src="/hero.jpg" alt="Example">
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
            'https://instagram.com/exampleco' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>ExampleCo on Instagram</title>
                        <meta name="description" content="نساعد فرق B2B على ترتيب الرسائل والعروض والتحويل من الموقع إلى المبيعات خلال خطط عملية قابلة للتنفيذ.">
                    </head>
                    <body>
                        <p>Book a call</p>
                        <a href="https://example.com">example.com</a>
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
            'https://competitor.test' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>Competitor Alpha</title>
                        <meta name="description" content="Competitor Alpha helps B2B teams improve conversion and demand capture with clear service pages.">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                    </head>
                    <body>
                        <h1>Clearer competitor offer</h1>
                        <a href="/services">Services</a>
                        <a href="/contact">Contact</a>
                        <a href="/privacy">Privacy</a>
                        <a href="/terms">Terms</a>
                        <a href="mailto:info@competitor.test">Info</a>
                        <p>Get started now and book a strategy call.</p>
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        $auditRun = AuditRun::query()->where('project_id', $project->id)->latest()->firstOrFail();

        $this->assertSame('completed', $auditRun->status);
        $this->assertSame('partial', data_get($auditRun->report_json, 'analysis_integrity.status'));
        $this->assertNotEmpty(data_get($auditRun->report_json, 'honest_diagnosis'));
        $this->assertSame('info@example.com', OfficialContact::query()
            ->where('audit_run_id', $auditRun->id)
            ->where('contact_type', 'official_email')
            ->value('contact_value'));
        $this->assertDatabaseMissing('official_contacts', [
            'audit_run_id' => $auditRun->id,
            'contact_value' => 'founder@example.com',
        ]);
        $this->assertDatabaseHas('monitor_snapshots', [
            'audit_run_id' => $auditRun->id,
            'project_id' => $project->id,
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Marketing Intelligence')
            ->assertSee('موثوقية التحليل')
            ->assertSee('info@example.com')
            ->assertSee('Competitor Alpha');
    }

    #[Test]
    public function audit_marks_report_insufficient_when_the_primary_site_is_unreachable(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject([
            'primary_domain' => 'offline.test',
            'official_social_links_json' => [],
            'competitors_json' => [],
        ]);

        Http::fake([
            'https://offline.test' => Http::response('', 503),
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        $auditRun = AuditRun::query()->where('project_id', $project->id)->latest()->firstOrFail();

        $this->assertSame('insufficient', data_get($auditRun->report_json, 'analysis_integrity.status'));
        $this->assertSame([], data_get($auditRun->report_json, 'priority_actions.improvements_30_days'));
        $this->assertSame([], data_get($auditRun->report_json, 'priority_actions.strategic_90_days'));

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('تم إخفاء الدرجات')
            ->assertSee('تحليل أولي ما زال ناقصاً');
    }

    #[Test]
    public function audit_keeps_partial_status_when_social_is_blocked_and_some_competitors_are_incomplete(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject([
            'official_social_links_json' => ['https://instagram.com/blocked-profile'],
            'competitors_json' => [
                ['label' => 'Incomplete Competitor', 'domain' => '', 'social_links' => []],
                ['label' => 'Competitor Alpha', 'domain' => 'competitor.test', 'social_links' => []],
            ],
        ]);

        Http::fake([
            'https://example.com' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>ExampleCo B2B Services</title>
                        <meta name="description" content="حلول واضحة تساعد فرق B2B على تنظيم الطلب وتحسين التحويل.">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                    </head>
                    <body>
                        <h1>وضوح العرض والتحويل</h1>
                        <a href="/contact">Contact</a>
                        <a href="mailto:info@example.com">info@example.com</a>
                        <p>Contact us to get started now.</p>
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
            'https://instagram.com/blocked-profile' => Http::response('', 403),
            'https://competitor.test' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>Competitor Alpha</title>
                        <meta name="description" content="Competitor Alpha helps B2B teams improve conversion with clearer messaging.">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                    </head>
                    <body>
                        <h1>Clear competitor</h1>
                        <a href="/contact">Contact</a>
                        <a href="mailto:info@competitor.test">info@competitor.test</a>
                        <p>Get started today.</p>
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        $auditRun = AuditRun::query()->where('project_id', $project->id)->latest()->firstOrFail();

        $this->assertSame('partial', data_get($auditRun->report_json, 'analysis_integrity.status'));
        $this->assertContains(
            'تجاهلنا المنافس "Incomplete Competitor" لأن بياناته لا تتضمن دوميناً أو روابط قابلة للتحليل.',
            data_get($auditRun->report_json, 'analysis_integrity.warnings', []),
        );
        $this->assertContains(
            'حاولنا قراءة حساباتك على التواصل الاجتماعي لكن لم نصل إلى أي صفحة عامة قابلة للتحليل.',
            data_get($auditRun->report_json, 'analysis_integrity.warnings', []),
        );
    }

    #[Test]
    public function audit_uses_manual_verified_social_profiles_when_platforms_block_automatic_fetch(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject([
            'official_social_links_json' => ['https://x.com/exampleco'],
            'verified_social_profiles_json' => [[
                'network' => 'X',
                'url' => 'https://x.com/exampleco',
                'handle' => '@exampleco',
                'title' => 'ExampleCo on X',
                'description' => 'حساب موثق يدوياً لأن المنصة تمنع القراءة العامة المباشرة.',
                'primary_cta' => 'تصفح الموقع',
                'links_back_to_site' => true,
                'verification_notes' => 'verified manually on 2026-04-23',
            ]],
            'competitors_json' => [],
        ]);

        Http::fake([
            'https://example.com' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>ExampleCo B2B Services</title>
                        <meta name="description" content="حلول واضحة تساعد فرق B2B على تنظيم الطلب وتحسين التحويل.">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                    </head>
                    <body>
                        <h1>وضوح العرض والتحويل</h1>
                        <a href="/contact">Contact</a>
                        <a href="mailto:info@example.com">info@example.com</a>
                        <p>Contact us to get started now.</p>
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
            'https://x.com/exampleco' => Http::sequence()
                ->push('', 403, ['Content-Type' => 'text/html'])
                ->push('', 403, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        $auditRun = AuditRun::query()->where('project_id', $project->id)->latest()->firstOrFail();

        $this->assertSame('partial', data_get($auditRun->report_json, 'analysis_integrity.status'));
        $this->assertSame(1, data_get($auditRun->report_json, 'analysis_integrity.counts.social_manual_verified'));
        $this->assertContains(
            'تعذّرت القراءة الآلية لحساباتك واعتمدنا على تحقق يدوي موثّق لبعضها.',
            data_get($auditRun->report_json, 'analysis_integrity.warnings', []),
        );
        $this->assertContains(
            'تم اعتماد 1 حساب موثّق يدوياً كدليل احتياطي.',
            data_get($auditRun->report_json, 'analysis_integrity.highlights', []),
        );
    }

    #[Test]
    public function audit_supports_social_only_projects_without_forcing_insufficient_status(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject([
            'primary_domain' => null,
            'official_social_links_json' => ['https://instagram.com/social-only-brand'],
            'competitors_json' => [],
        ]);

        Http::fake([
            'https://instagram.com/social-only-brand' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <title>Social Only Brand</title>
                        <meta name="description" content="علامة تشرح خدمتها بوضوح وتدعو لحجز مكالمة مباشرة.">
                    </head>
                    <body>
                        <p>Book a call</p>
                        <a href="https://wa.me/966500000000">WhatsApp</a>
                    </body>
                </html>
            HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        $auditRun = AuditRun::query()->where('project_id', $project->id)->latest()->firstOrFail();

        $this->assertSame('partial', data_get($auditRun->report_json, 'analysis_integrity.status'));
        $this->assertSame(0, data_get($auditRun->report_json, 'analysis_integrity.counts.website_readable'));
        $this->assertSame(1, data_get($auditRun->report_json, 'analysis_integrity.counts.social_accessible'));
        $this->assertNotEmpty(data_get($auditRun->report_json, 'priority_actions.quick_wins_7_days'));

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('تم إخفاء executive scores')
            ->assertSee('تحليل جزئي يحتاج استكمال');
    }

    #[Test]
    public function audit_accepts_manual_verified_social_profiles_even_without_profile_urls(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject([
            'primary_domain' => null,
            'official_social_links_json' => [],
            'verified_social_profiles_json' => [[
                'network' => 'Instagram',
                'handle' => '@manualsocial',
                'title' => 'Manual Social Brand',
                'description' => 'حساب موثق يدوياً داخل المشروع قبل ربط URL نهائي.',
                'primary_cta' => 'راسلنا الآن',
                'links_back_to_site' => false,
                'verification_notes' => 'verified manually on 2026-04-23',
            ]],
            'competitors_json' => [],
        ]);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        $auditRun = AuditRun::query()->where('project_id', $project->id)->latest()->firstOrFail();

        $this->assertSame('partial', data_get($auditRun->report_json, 'analysis_integrity.status'));
        $this->assertSame(1, data_get($auditRun->report_json, 'analysis_integrity.counts.social_requested'));
        $this->assertSame(1, data_get($auditRun->report_json, 'analysis_integrity.counts.social_manual_verified'));
        $this->assertNotContains(
            'لا توجد روابط حسابات تواصل اجتماعي مؤكدة في مشروعك.',
            data_get($auditRun->report_json, 'analysis_integrity.warnings', []),
        );
        $this->assertContains(
            'تعذّرت القراءة الآلية لحساباتك واعتمدنا على تحقق يدوي موثّق لبعضها.',
            data_get($auditRun->report_json, 'analysis_integrity.warnings', []),
        );
    }

    #[Test]
    public function audit_request_queues_a_job_and_persists_a_queued_run(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject();

        Queue::fake();

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        Queue::assertPushed(RunProjectIntelligenceAuditJob::class);

        $auditRun = AuditRun::query()->where('project_id', $project->id)->latest()->firstOrFail();

        $this->assertSame('queued', $auditRun->status);
        $this->assertSame('تمت جدولة تحليل مشروعك', data_get($auditRun->summary_json, 'headline'));
    }

    #[Test]
    public function audit_request_does_not_create_another_run_when_one_is_already_active(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject();

        AuditRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'status' => 'running',
            'trigger_source' => 'manual',
            'started_at' => now()->subMinute(),
        ]);

        Queue::fake();

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        Queue::assertNothingPushed();
        $this->assertSame(1, AuditRun::query()->where('project_id', $project->id)->count());
    }

    #[Test]
    public function failed_audits_are_persisted_instead_of_rolling_back_silently(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceProject();

        $analyzer = Mockery::mock(WebsiteAuditAnalyzer::class);
        $analyzer->shouldReceive('analyze')->andThrow(new \RuntimeException('forced failure during fetch'));
        $this->app->instance(WebsiteAuditAnalyzer::class, $analyzer);

        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('projects.audit.run', $project))
            ->assertRedirect(route('projects.show', $project));

        $auditRun = AuditRun::query()->where('project_id', $project->id)->latest()->firstOrFail();

        $this->assertSame('failed', $auditRun->status);
        $this->assertNotNull($auditRun->failed_at);
        $this->assertSame('analysis_failed', data_get($auditRun->error_json, 'code'));
        $this->assertSame('تعذّر إكمال تحليل مشروعك', data_get($auditRun->summary_json, 'headline'));
    }

    /**
     * @return array{user: User, workspace: Workspace, project: Project}
     */
    private function makeWorkspaceProject(array $overrides = []): array
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Intelligence Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Intelligence Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        app(OnboardingState::class)->markCompleted($workspace);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Intelligence Client',
            'status' => 'active',
        ]);

        $project = Project::query()->create(array_merge([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Intelligence Project',
            'stage' => 3,
            'status' => 'active',
            'sector' => 'b2b_services',
            'market_country' => 'SA',
            'primary_domain' => 'example.com',
            'official_social_links_json' => ['https://instagram.com/exampleco'],
            'verified_social_profiles_json' => [],
            'competitors_json' => [
                ['label' => 'Competitor Alpha', 'domain' => 'competitor.test', 'social_links' => []],
            ],
            'analysis_goals_json' => ['improve_marketing', 'rank_in_search'],
            'monitoring_enabled' => true,
        ], $overrides));

        return [
            'user' => $user,
            'workspace' => $workspace,
            'project' => $project,
        ];
    }
}
