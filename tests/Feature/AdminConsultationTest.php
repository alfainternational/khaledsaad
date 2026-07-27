<?php

namespace Tests\Feature;

use App\Models\ConsultationBlueprint;
use App\Models\QuestionVersion;
use App\Models\User;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
    }

    #[Test]
    public function only_admins_can_govern_the_consultation_catalog(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.consultations.index'))->assertNotFound();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get(route('admin.consultations.index'))->assertOk()->assertSee('الاستشارة التسويقية الذكية');
    }

    #[Test]
    public function publishing_uses_an_independent_draft_and_never_mutates_history(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $blueprint = ConsultationBlueprint::where('key', 'smart-marketing-consultation')->firstOrFail();
        $oldVersion = $blueprint->currentVersion;
        $oldQuestion = QuestionVersion::whereHas('definition', fn ($q) => $q->where('key', 'START-01'))
            ->where('version', $oldVersion->version)->firstOrFail();
        $originalText = $oldQuestion->user_text;

        $response = $this->actingAs($admin)->post(route('admin.consultations.drafts.store', $blueprint))->assertRedirect();
        preg_match('~/versions/(\d+)~', $response->headers->get('Location'), $match);
        $draft = $blueprint->versions()->findOrFail((int) $match[1]);
        $draftQuestion = QuestionVersion::where('question_definition_id', $oldQuestion->question_definition_id)
            ->where('version', $draft->version)->firstOrFail();

        $this->put(route('admin.consultations.questions.update', [$draft, $draftQuestion]), [
            'user_text' => 'ما النطاق الذي تريد تشخيصه الآن؟',
            'required' => 1,
            'allow_unknown' => 1,
        ])->assertRedirect();
        $this->post(route('admin.consultations.publish', $draft))->assertRedirect();

        $this->assertSame($originalText, $oldQuestion->refresh()->user_text);
        $this->assertSame('published', $draft->refresh()->status);
        $this->assertNotNull($draft->locked_at);
        $this->assertSame($draft->id, $blueprint->refresh()->current_version_id);
        $this->put(route('admin.consultations.questions.update', [$draft, $draftQuestion]), ['user_text' => 'محاولة تعديل'])
            ->assertSessionHasErrors('version');
    }
}
