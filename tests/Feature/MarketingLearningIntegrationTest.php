<?php

namespace Tests\Feature;

use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingLearningIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_project_and_dashboard_do_not_create_or_promote_learning_applications(): void
    {
        [$user, $project] = $this->project();

        $this->actingAs($user)->get(route('app.projects.show', $project))
            ->assertOk()
            ->assertSee('لفهم الفجوة قبل تنفيذها')
            ->assertDontSee('ابدأ المهمة');

        $this->actingAs($user)->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee('خطوتك التسويقية التالية');

        $this->assertDatabaseCount('marketing_learning_runs', 0);
    }

    public function test_failed_review_keeps_answers_and_offers_plain_language_retry(): void
    {
        [$user, $project] = $this->project();
        $user = app(ExperienceService::class)->activate($user, Experience::LEARNING);
        $run = MarketingLearningRun::startFor($project, $user);
        $attempt = $run->attemptFor('describe-real-customer');
        $attempt->update([
            'answers' => [
                'customer_profile' => 'صاحب متجر صغير يريد زيادة مبيعاته ويعمل وحده',
                'customer_problem' => 'تصل زيارات إلى متجره لكن الطلبات المكتملة قليلة',
                'buying_trigger' => 'يبدأ البحث بعد انخفاض الطلبات لمدة أسبوعين',
            ],
            'status' => MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
            'failure_reason' => 'API provider model timeout with secret details',
        ]);

        $response = $this->actingAs($user)->get(route('app.learning.marketing.course.result', [
            'exercise' => $attempt->exercise_key,
            'project' => $project->slug,
        ]));

        $response->assertOk()
            ->assertSee('حفظنا إجاباتك')
            ->assertSee('أعد المراجعة')
            ->assertDontSee('API')
            ->assertDontSee('model')
            ->assertDontSee('timeout');
        $this->assertSame('صاحب متجر صغير يريد زيادة مبيعاته ويعمل وحده', $attempt->refresh()->answers['customer_profile']);
    }

    /** @return array{0: User, 1: Project} */
    private function project(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع تسويقي']);
        $project->brainFacts()->delete();
        $user = app(ExperienceService::class)->selectInitial($user, Experience::BUSINESS);

        return [$user, $project];
    }
}
