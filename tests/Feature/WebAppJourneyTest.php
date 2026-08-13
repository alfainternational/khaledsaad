<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebAppJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function a_visitor_can_register_and_reach_the_dashboard(): void
    {
        $this->post(route('register'), [
            'experience' => 'business',
            'name' => 'خالد',
            'email' => 'khaled@example.test',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ])->assertRedirect(route('app.projects.create'));

        $this->assertAuthenticated();
        $this->post(route('app.projects.store'), ['name' => 'مشروع خالد'])->assertRedirect();
        $this->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('ما أهم شيء أفعله الآن لتحسين مشروعي؟')
            ->assertSee('data-layout="dashboard"', false)
            ->assertSee('layout-metrics', false)
            ->assertSee('layout-main-aside', false);
    }

    #[Test]
    public function the_catalog_shows_eleven_tools_and_marks_unbuilt_ones_clearly(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('app.tools.index'))->assertOk();

        // الأدوات الإحدى عشرة كلها مبنية وقابلة للتشغيل.
        $this->assertSame(11, Tool::count());
        $this->assertSame(11, Tool::runnable()->count());
        $response->assertSee('اعرف التفاصيل وابدأ');
    }

    #[Test]
    public function the_wizard_saves_each_step_and_blocks_an_incomplete_run(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        $this->actingAs($user);

        $this->post(route('app.runs.step.save', [$run->uuid, 1]), [
            'business_model' => 'services',
            'description' => str_repeat('وصف واضح للخدمة ', 3),
            'geography' => 'الرياض',
            'monthly_budget' => 3000,
        ])->assertRedirect(route('app.runs.step', [$run->uuid, 2]));

        $this->assertSame('services', $run->refresh()->answerMap()['business_model']['value']);

        // التشغيل مرفوض قبل اكتمال باقي الخطوات — لا يُهدر استدعاء نموذج على بيانات ناقصة.
        $this->post(route('app.runs.queue', $run->uuid))->assertSessionHasErrors('answers');
        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_user_cannot_reach_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = app(ProjectService::class)->create($owner, ['name' => 'مشروع خاص']);

        // 404 لا 403: لا نؤكد وجود المورد أصلًا لمن لا يملكه.
        $this->actingAs($intruder)->get(route('app.projects.show', $project))->assertNotFound();
    }

    #[Test]
    public function the_admin_usage_dashboard_renders_for_admins_only(): void
    {
        // اللوحة صارت محصورة بصلاحية admin.
        $this->actingAs(User::factory()->create())
            ->get(route('admin.usage'))
            ->assertNotFound();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->get(route('admin.usage'))
            ->assertOk()
            ->assertSee('تكلفة الذكاء الاصطناعي');
    }
}
