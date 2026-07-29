<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\BrainReader;
use App\Modules\Brain\BrainWriter;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * التعارض يُعلَن للمراجعة ولا يُحسم صامتًا (§٩).
 *
 * العطل الذي يحرسه هذا الملف: التعارضات كانت تُسجَّل أحداثًا صحيحة، ثم لا
 * يراها أحد — لا شاشة ولا عقد ولا تنبيه. أي أن «يُعلَّم للمراجعة» لم تكن
 * تعني شيئًا، والمعلومة الأنفع في الدماغ — أن نشاطًا يقول شيئًا وبياناته
 * تقول غيره — كانت تُدفَن في جدول.
 */
class ConflictReviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_conflict_travels_with_both_sides_not_just_its_existence(): void
    {
        $project = $this->conflicted();

        $conflicts = app(BrainReader::class)->openConflictsWithValues($project);

        $this->assertCount(1, $conflicts);
        $this->assertSame('geography', $conflicts[0]['key']);

        $values = array_column($conflicts[0]['sides'], 'value');
        $sources = array_column($conflicts[0]['sides'], 'source');

        // «مصدران اختلفا» بلا القولين ليست معلومة يُتخذ عليها قرار.
        $this->assertEqualsCanonicalizing(['الرياض', 'جدة'], $values);
        $this->assertEqualsCanonicalizing(['Intake', 'AiReadiness'], $sources);
    }

    #[Test]
    public function the_owner_sees_the_conflict_on_the_diagnosis_screen(): void
    {
        [$user, $project] = $this->conflictedForOwner();

        $this->actingAs($user)
            ->get(route('app.readiness.show', $project))
            ->assertOk()
            ->assertSee('تحتاج مراجعتك')
            ->assertSee('الرياض')
            ->assertSee('جدة');
    }

    #[Test]
    public function a_clean_brain_shows_no_review_section(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاط متسق']);

        $this->actingAs($user)
            ->get(route('app.readiness.show', $project))
            ->assertOk()
            ->assertDontSee('تحتاج مراجعتك');
    }

    #[Test]
    public function the_api_carries_the_same_conflicts_as_the_web(): void
    {
        [$user, $project] = $this->conflictedForOwner();

        $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.readiness.show', $project))
            ->assertOk()
            ->assertJsonStructure(['data' => ['conflicts' => [['key', 'sides']]]]);
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function conflictedForOwner(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاط متعارض']);
        $project->brainFacts()->delete();

        $this->recordBothSides($project->fresh());

        return [$user, $project->fresh()];
    }

    private function conflicted(): Project
    {
        [, $project] = $this->conflictedForOwner();

        return $project;
    }

    private function recordBothSides(Project $project): void
    {
        $brain = app(BrainWriter::class);

        $brain->record($project, 'geography', 'الرياض', EvidenceLevel::Inferred, 'Intake');

        // مصدر مستقل يقول غير ما قاله صاحب النشاط: تُحفظ الروايتان معًا.
        $brain->record($project, 'geography', 'جدة', EvidenceLevel::Measured, 'AiReadiness');
    }
}
