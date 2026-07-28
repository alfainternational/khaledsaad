<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Projects\ProjectService;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
    }

    #[Test]
    public function web_and_api_share_the_same_session_and_answer_state(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع موحد', 'stage' => 'growth']);
        Sanctum::actingAs($user);

        $created = $this->postJson(route('api.v1.consultations.store', $project), ['depth' => 'standard'])
            ->assertCreated()
            ->assertJsonPath('data.question.key', 'START-01');
        $uuid = $created->json('data.uuid');

        $this->putJson(route('api.v1.consultations.answer', [$uuid, 'START-01']), ['value' => 'مشروع قائم'])
            ->assertOk()
            ->assertJsonPath('data.question.key', 'START-02');

        $this->actingAs($user)->get(route('app.consultations.show', $uuid))
            ->assertOk()
            ->assertSee('ما الذي يقدمه مشروعك للعميل؟');
    }

    #[Test]
    public function another_user_receives_not_found_for_the_session(): void
    {
        $owner = User::factory()->create();
        $project = app(ProjectService::class)->create($owner, ['name' => 'خاص', 'stage' => 'growth']);
        Sanctum::actingAs($owner);
        $uuid = $this->postJson(route('api.v1.consultations.store', $project))->json('data.uuid');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson(route('api.v1.consultations.show', $uuid))->assertNotFound();
    }

    #[Test]
    public function project_page_makes_the_unified_consultation_the_primary_action(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'واجهة موحدة', 'stage' => 'growth']);

        $this->actingAs($user)->get(route('app.projects.show', $project))
            ->assertOk()
            ->assertSee('ابدأ تشخيص مشروعك')
            ->assertSee(route('app.consultations.start', $project), false);
    }
}
