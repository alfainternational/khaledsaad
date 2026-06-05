<?php

namespace Tests\Feature\Auth;

use App\Domain\Workspace\Models\Workspace;
use App\Jobs\RunProjectIntelligenceAuditJob;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function onboarding_creates_first_client_project_and_profile_data(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $this->post(route('register.store'), [
            'name' => 'Onboarding User',
            'email' => 'onboarding@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $workspace = Workspace::query()->firstOrFail();

        $this->post(route('onboarding.store'), [
            'account_name' => 'Growth Account',
            'workspace_name' => 'Growth Workspace',
            'workspace_type' => 'agency',
            'persona' => 'agency',
            'awareness_level' => 'expert',
            'primary_goal' => 'launch_campaigns',
            'recommended_path' => 'agency_delivery',
            'audience' => 'Founders',
            'country' => 'السعودية',
            'content_locale' => 'ar_gulf',
            'current_challenge' => 'Lack of campaign structure',
            'client_name' => 'Client Prime',
            'project_name' => 'Conversion Sprint',
            'project_stage' => 2,
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('clients', [
            'workspace_id' => $workspace->id,
            'name' => 'Client Prime',
        ]);

        $this->assertDatabaseHas('projects', [
            'workspace_id' => $workspace->id,
            'name' => 'Conversion Sprint',
            'stage' => 2,
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'key' => 'system.onboarding_completed',
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'key' => 'business.profile',
        ]);

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $workspace->projects()->firstOrFail()->id,
            'key' => 'project.marketing_brief',
        ]);

        $this->assertSame('agency', data_get($workspace->fresh()->workspaceData()->where('key', 'business.profile')->first()?->value_json, 'persona'));
    }

    #[Test]
    public function onboarding_normalizes_legacy_frontend_defaults_and_completes_successfully(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $this->post(route('register.store'), [
            'name' => 'Legacy Defaults User',
            'email' => 'legacy-defaults@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $workspace = Workspace::query()->firstOrFail();

        $this->post(route('onboarding.store'), [
            'account_name' => 'Legacy Account',
            'workspace_name' => 'Legacy Workspace',
            'workspace_type' => 'agency',
            'persona' => 'agency',
            'awareness_level' => 'aware',
            'primary_goal' => 'clarify_message',
            'recommended_path' => '',
            'audience' => '',
            'country' => 'السعودية',
            'content_locale' => '',
            'current_challenge' => '',
            '_challenge_hint' => 'agency_manage',
            'client_name' => 'Legacy Client',
            'project_name' => 'Legacy Project',
            'project_stage' => 4,
            'brief_business_summary' => 'خدمة تبني وضوحاً تسويقياً وتشغيلياً للوكالات.',
            'brief_offer' => 'نظام تشغيل وتسويق يساعد الوكالات على تنظيم العميل والمخرج.',
            'brief_ideal_customer' => 'وكالات تسويق واستشارات لديها عدة عملاء.',
        ])->assertRedirect(route('dashboard'));

        $profile = $workspace->fresh()
            ->workspaceData()
            ->where('key', 'business.profile')
            ->firstOrFail()
            ->value_json;

        $this->assertSame('expert', data_get($profile, 'awareness_level'));
        $this->assertSame('clarify_idea', data_get($profile, 'primary_goal'));
        $this->assertSame('agency_delivery', data_get($profile, 'recommended_path'));
        $this->assertSame('وكالات تسويق واستشارات لديها عدة عملاء.', data_get($profile, 'audience'));
        $this->assertSame('ar_modern_fusha', data_get($profile, 'content_locale'));
        $this->assertSame('إدارة عملاء متعددين وتنظيم العمل', data_get($profile, 'current_challenge'));
    }

    #[Test]
    public function onboarding_with_a_domain_queues_an_intelligence_audit(): void
    {
        Queue::fake();
        $this->seed(PlatformBootstrapSeeder::class);

        $this->post(route('register.store'), [
            'name' => 'Domain User',
            'email' => 'domain-user@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->post(route('onboarding.store'), [
            'account_name' => 'Domain Account',
            'workspace_name' => 'Domain Workspace',
            'workspace_type' => 'personal',
            'persona' => 'business',
            'awareness_level' => 'aware',
            'primary_goal' => 'improve_marketing',
            'audience' => 'Local shoppers',
            'country' => 'السعودية',
            'content_locale' => 'ar_modern_fusha',
            'client_name' => 'My Store',
            'project_name' => 'Store Launch',
            'project_stage' => 2,
            'sector' => 'ecommerce',
            'primary_domain' => 'https://example.com',
        ])->assertRedirect(route('dashboard'));

        Queue::assertPushed(RunProjectIntelligenceAuditJob::class);
    }

    #[Test]
    public function onboarding_without_a_crawlable_target_does_not_queue_an_audit(): void
    {
        Queue::fake();
        $this->seed(PlatformBootstrapSeeder::class);

        $this->post(route('register.store'), [
            'name' => 'No Domain User',
            'email' => 'no-domain-user@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->post(route('onboarding.store'), [
            'account_name' => 'No Domain Account',
            'workspace_name' => 'No Domain Workspace',
            'workspace_type' => 'personal',
            'persona' => 'business',
            'awareness_level' => 'aware',
            'primary_goal' => 'improve_marketing',
            'audience' => 'Local shoppers',
            'country' => 'السعودية',
            'content_locale' => 'ar_modern_fusha',
            'client_name' => 'My Store',
            'project_name' => 'Store Launch',
            'project_stage' => 2,
        ])->assertRedirect(route('dashboard'));

        Queue::assertNotPushed(RunProjectIntelligenceAuditJob::class);
    }
}
