<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MarketingLearningRun;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExperienceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_forward_compatible_experience_columns(): void
    {
        foreach ([
            'initial_experience',
            'active_experience',
            'business_experience_enabled_at',
            'learning_experience_enabled_at',
            'experience_selected_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column), $column);
        }
    }

    public function test_user_experience_helpers_use_timestamps_as_the_source_of_truth(): void
    {
        foreach (['hasBusinessExperience', 'hasLearningExperience', 'activeExperience'] as $method) {
            $this->assertTrue(method_exists(User::class, $method), $method);
        }

        $user = User::factory()->create([
            'initial_experience' => 'learning',
            'active_experience' => 'learning',
            'business_experience_enabled_at' => null,
            'learning_experience_enabled_at' => now(),
            'experience_selected_at' => now(),
        ]);

        $this->assertFalse($user->hasBusinessExperience());
        $this->assertTrue($user->hasLearningExperience());
        $this->assertSame('learning', $user->activeExperience()?->value);
    }

    public function test_backfill_enables_business_for_project_owners_and_both_experiences_for_admins(): void
    {
        $this->assertTrue(class_exists(\App\Support\Experience\ExperienceBackfill::class));

        $owner = User::factory()->withoutExperience()->create();
        app(ProjectService::class)->create($owner, ['name' => 'مشروع قائم']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertSame(2, app(\App\Support\Experience\ExperienceBackfill::class)->run());

        $owner->refresh();
        $this->assertSame('business', $owner->initial_experience->value);
        $this->assertSame('business', $owner->active_experience->value);
        $this->assertNotNull($owner->business_experience_enabled_at);
        $this->assertNull($owner->learning_experience_enabled_at);

        $admin->refresh();
        $this->assertSame('business', $admin->active_experience->value);
        $this->assertNotNull($admin->business_experience_enabled_at);
        $this->assertNotNull($admin->learning_experience_enabled_at);
    }

    public function test_backfill_ignores_empty_learning_runs_but_classifies_real_learning(): void
    {
        $emptyUser = User::factory()->withoutExperience()->create();
        $emptyRun = MarketingLearningRun::startForWorkspace($emptyUser->primaryWorkspace(), $emptyUser);
        $emptyRun->attemptFor('marketing-reality-check');

        $learner = User::factory()->withoutExperience()->create();
        $realRun = MarketingLearningRun::startForWorkspace($learner->primaryWorkspace(), $learner);
        $realRun->attemptFor('marketing-reality-check')->update([
            'answers' => ['answer' => 'إجابة فعلية محفوظة'],
        ]);

        $this->assertSame(1, app(\App\Support\Experience\ExperienceBackfill::class)->run());

        $emptyUser->refresh();
        $this->assertNull($emptyUser->initial_experience);
        $this->assertNull($emptyUser->active_experience);
        $this->assertNull($emptyUser->experience_selected_at);

        $learner->refresh();
        $this->assertSame('learning', $learner->initial_experience?->value);
        $this->assertSame('learning', $learner->active_experience?->value);
        $this->assertNotNull($learner->learning_experience_enabled_at);
        $this->assertNull($learner->business_experience_enabled_at);
    }

    public function test_backfill_preserves_combined_user_projects_runs_and_attempts(): void
    {
        $user = User::factory()->withoutExperience()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع محفوظ']);
        $run = MarketingLearningRun::startForWorkspace($user->primaryWorkspace(), $user, $project);
        $attempt = $run->attemptFor('marketing-reality-check');
        $attempt->update(['answers' => ['reality_snapshot' => 'إجابة محفوظة']]);

        $this->assertSame(1, app(\App\Support\Experience\ExperienceBackfill::class)->run());

        $user->refresh();
        $this->assertTrue($user->hasBusinessExperience());
        $this->assertTrue($user->hasLearningExperience());
        $this->assertSame('business', $user->activeExperience()?->value);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('marketing_learning_runs', ['id' => $run->id, 'project_id' => $project->id]);
        $this->assertDatabaseHas('marketing_exercise_attempts', ['id' => $attempt->id]);
    }
}
