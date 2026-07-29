<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\Jobs\ProbeAnswerPresence;
use App\Modules\AiReadiness\Models\PresenceProbe;
use App\Modules\AiReadiness\Models\PresenceRun;
use App\Services\Billing\Entitlements;
use App\Services\Projects\ProjectService;
use App\Support\Billing\FeatureKey;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * رحلة تقرير الحضور: من الشاشة إلى الطابور، وبنفس العقد في الويب والتطبيق.
 */
class PresenceJourneyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_owner_sees_the_questions_and_the_remaining_budget(): void
    {
        [$user, $project] = $this->ownedProject();

        $response = $this->actingAs($user)
            ->get(route('app.presence.show', $project))
            ->assertOk();

        // الأسئلة تُعرض قبل الاستطلاع: صاحب النشاط يرى بماذا سيُقاس.
        $response->assertSee('مين أفضل');
        $response->assertSee('متبقٍّ من سقف هذا الشهر');
        $response->assertSee('لم يُشغَّل استطلاع بعد، فلا رقم يُعرض.');
    }

    #[Test]
    public function starting_a_probe_queues_it_instead_of_blocking_the_request(): void
    {
        Queue::fake();
        [$user, $project] = $this->ownedProject();

        $this->actingAs($user)
            ->post(route('app.presence.probe', $project))
            ->assertRedirect(route('app.presence.show', $project));

        Queue::assertPushed(ProbeAnswerPresence::class);
    }

    #[Test]
    public function an_exhausted_budget_refuses_before_anything_is_queued(): void
    {
        Queue::fake();
        [$user, $project] = $this->ownedProject();
        $project->workspace->forceFill(['monthly_query_limit' => 2])->save();

        $this->actingAs($user)
            ->post(route('app.presence.probe', $project))
            ->assertSessionHasErrors('budget');

        // الرفض قبل الطابور: مهمة تدخل ثم تفشل تترك المستخدم ينتظر بلا سبب.
        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_report_shows_both_ratios_with_distinct_labels(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->completedRun($project);

        $response = $this->actingAs($user)
            ->get(route('app.presence.show', $project))
            ->assertOk();

        /*
         * §١٢: المقياسان لا يُعرضان معًا بلا تسميتين ظاهرتين. مقاماهما مختلفان
         * تمامًا، وخلطهما يعطي صاحب النشاط قراءة معكوسة عن موقعه.
         */
        $response->assertSee('معدّل الذكر — كم مرة ذُكرت من محاولاتنا');
        $response->assertSee('حصة الصوت — نصيبك من ذكر كل العلامات');

        // «٢ من ٣» لا «ظاهر».
        $response->assertSee('2 من 3');
    }

    #[Test]
    public function the_api_returns_the_same_contract_as_the_web(): void
    {
        [$user, $project] = $this->ownedProject();
        $this->completedRun($project);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.presence.show', $project))
            ->assertOk();

        $response->assertJsonStructure(['data' => [
            'metrics' => ['mention_rate', 'share_of_voice', 'citation_rate', 'per_question', 'basis', 'publishable'],
            'source_map' => ['available'],
            'questions',
            'budget' => ['monthly_limit', 'remaining', 'usage_ratio'],
        ]]);
    }

    #[Test]
    public function another_workspace_cannot_reach_the_report(): void
    {
        [, $project] = $this->ownedProject();
        $stranger = $this->entitledUser();

        $this->actingAs($stranger)
            ->get(route('app.presence.show', $project))
            ->assertNotFound();
    }

    private function completedRun(Project $project): PresenceRun
    {
        $run = PresenceRun::create([
            'project_id' => $project->id,
            'provider' => 'fake',
            'model' => 'fake-1',
            'questions_count' => 1,
            'attempts_per_question' => 3,
            'status' => PresenceRun::STATUS_COMPLETED,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        foreach ([[1, true], [2, true], [3, false]] as [$attempt, $mentioned]) {
            PresenceProbe::create([
                'presence_run_id' => $run->id,
                'question_key' => 'best_provider',
                'question' => 'مين أفضل محمصة قهوة في الرياض؟',
                'attempt' => $attempt,
                'brand_mentioned' => $mentioned,
                'brands_mentioned' => $mentioned ? ['نشاطي', 'منافس أ'] : ['منافس أ'],
                'citations' => ['https://source.example/page'],
                'raw_response' => 'نص الجواب',
                'status' => PresenceProbe::STATUS_OK,
            ]);
        }

        return $run;
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function ownedProject(): array
    {
        $user = $this->entitledUser();

        $project = app(ProjectService::class)->create($user, [
            'name' => 'نشاطي',
            'industry' => 'محمصة قهوة',
            'geography' => 'الرياض',
            'website' => 'https://example.test',
        ]);

        $project->workspace->forceFill(['monthly_query_limit' => 100])->save();

        return [$user, $project->fresh()];
    }

    /**
     * مستخدم على خطة تشمل التشخيص الكامل — الحدّ بين المستويين ٠ و١.
     */
    private function entitledUser(): User
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);

        PlanFeature::updateOrCreate(
            [
                'plan_id' => Plan::where('key', 'free')->value('id'),
                'feature_id' => Feature::where('key', FeatureKey::DIAGNOSIS_FULL)->value('id'),
            ],
            ['enabled' => true, 'value' => null],
        );

        app(Entitlements::class)->flush();

        return User::factory()->create();
    }
}
