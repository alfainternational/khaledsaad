<?php

namespace Tests\Feature;

use App\Http\Controllers\App\ToolCatalogController;
use App\Jobs\RunToolPipeline;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\RunPresenter;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * التطابق بين الويب والتطبيق مضمون بالبنية: الطرفان يستدعيان نفس العارض.
 * هذه الاختبارات تثبت أن الضمان قائم فعلًا ولم ينكسر.
 */
class ApiParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function the_api_and_the_web_expose_an_identical_tool_catalog(): void
    {
        $apiCatalog = $this->getJson(route('api.v1.tools.index'))->assertOk()->json('data');
        $webCatalog = app(ToolCatalogController::class)->catalog();

        $this->assertSame($webCatalog, $apiCatalog);
        $this->assertCount(11, $apiCatalog);
    }

    #[Test]
    public function the_api_and_the_web_expose_an_identical_run_payload(): void
    {
        $user = User::factory()->create();
        $run = $this->draftRun($user);

        Sanctum::actingAs($user);

        $apiPayload = $this->getJson(route('api.v1.runs.show', $run->uuid))->assertOk()->json('data');
        $webPayload = app(RunPresenter::class)->wizard($run->refresh());

        $this->assertSame(json_decode(json_encode($webPayload), true), $apiPayload);
    }

    #[Test]
    public function a_device_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson(route('api.v1.auth.register'), [
            'name' => 'خالد',
            'email' => 'device@example.test',
            'password' => 'password-1234',
            'device_name' => 'Pixel 9',
        ])->assertCreated();

        $this->assertNotEmpty($response->json('data.token'));

        // المفتاح لا يغادر الخادم: الاستجابة تحمل رمز مستخدم فقط.
        $this->assertStringNotContainsString('deepseek', strtolower($response->getContent()));
    }

    #[Test]
    public function the_api_saves_a_step_and_queues_a_complete_run(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $run = $this->draftRun($user);
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.runs.step', [$run->uuid, 4]), [
            'answers' => [
                'landing_experience' => 'optimized',
                'retention_motion' => 'systematic',
                'known_cac' => 90,
            ],
        ])->assertOk()->assertJsonPath('data.completeness_percent', 100);

        $this->postJson(route('api.v1.runs.queue', $run->uuid))
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(RunToolPipeline::class);
    }

    #[Test]
    public function unauthenticated_devices_are_rejected(): void
    {
        $this->getJson(route('api.v1.projects.index'))->assertUnauthorized();
    }

    #[Test]
    public function a_device_cannot_read_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = app(ProjectService::class)->create($owner, ['name' => 'خاص']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.projects.show', $project->slug))->assertNotFound();
    }

    private function draftRun(User $user): ToolRun
    {
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع API']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        $steps = [
            1 => ['business_model' => 'saas', 'description' => str_repeat('منتج اشتراك واضح ', 3), 'geography' => 'الخليج', 'monthly_budget' => 8000],
            2 => ['primary_goal' => 'sales', 'value_proposition' => 'توفير ساعتين يوميًا لكل مستخدم', 'audience_clarity' => 'rough'],
            3 => ['active_channels' => ['seo'], 'tracking_maturity' => 'full', 'content_cadence' => 'weekly'],
        ];

        foreach ($steps as $step => $input) {
            app(ToolRunService::class)->saveStep($run, $step, $input);
        }

        return $run->refresh();
    }
}
