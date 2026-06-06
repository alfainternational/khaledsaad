<?php

namespace Tests\Feature\Projects;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectMarketingBriefTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_store_project_marketing_brief_and_sync_profile_derivatives(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $this->post(route('register.store'), [
            'name' => 'Brief User',
            'email' => 'brief@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $workspace = Workspace::query()->firstOrFail();

        $this->post(route('onboarding.store'), [
            'account_name' => 'Brief Account',
            'workspace_name' => 'Brief Workspace',
            'workspace_type' => 'personal',
            'persona' => 'business',
            'awareness_level' => 'aware',
            'primary_goal' => 'improve_marketing',
            'recommended_path' => 'growth_system',
            'audience' => 'أصحاب مشاريع قائمة',
            'country' => 'السعودية',
            'content_locale' => 'ar_gulf',
            'current_challenge' => 'ضعف الوضوح في الرسالة',
            'client_name' => 'Brief Client',
            'project_name' => 'Brief Project',
            'project_stage' => 3,
        ]);

        $project = Project::query()->where('workspace_id', $workspace->id)->firstOrFail();

        $this->put(route('projects.brief.update', $project), [
            'business' => [
                'summary' => 'نقدم خدمات تسويق تشغيلي للمشاريع القائمة.',
                'offer' => 'خطة تسويق ومحتوى وتنفيذ مرتب.',
                'market' => 'السعودية',
            ],
            'audience' => [
                'ideal_customer' => 'أصحاب مشاريع قائمة يحتاجون وضوحاً ونمواً.',
                'pain_points' => 'يبذلون جهداً بلا رؤية موحدة.',
            ],
            'goals' => [
                'primary_goal' => 'رفع جودة العملاء المحتملين',
                'success_metric' => 'عدد الاستفسارات المؤهلة',
            ],
            'current_marketing' => [
                'channels' => 'إنستغرام، واتساب، إعلانات Meta',
            ],
            'execution' => [
                'priority' => 'إعادة بناء العرض والرسائل',
            ],
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('workspace_data', [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'project.marketing_brief',
        ]);

        $profile = $workspace->fresh()->workspaceData()->where('key', 'business.profile')->firstOrFail()->value_json;

        $this->assertSame('أصحاب مشاريع قائمة يحتاجون وضوحاً ونمواً.', data_get($profile, 'audience'));
        $this->assertSame('improve_marketing', data_get($profile, 'primary_goal'));
        $this->assertSame('إعادة بناء العرض والرسائل', data_get($profile, 'current_challenge'));
    }
}
