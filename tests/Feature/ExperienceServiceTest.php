<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Experience\Experience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_selection_enables_only_the_selected_experience(): void
    {
        $this->assertTrue(class_exists(\App\Support\Experience\ExperienceService::class));
        $user = User::factory()->withoutExperience()->create();

        app(\App\Support\Experience\ExperienceService::class)
            ->selectInitial($user, Experience::LEARNING);

        $user->refresh();
        $this->assertSame('learning', $user->initial_experience?->value);
        $this->assertSame('learning', $user->active_experience?->value);
        $this->assertTrue($user->hasLearningExperience());
        $this->assertFalse($user->hasBusinessExperience());
        $this->assertNotNull($user->experience_selected_at);
    }

    public function test_activation_and_switch_preserve_the_initial_choice_and_user_data(): void
    {
        $this->assertTrue(class_exists(\App\Support\Experience\ExperienceService::class));
        $this->assertTrue(method_exists(\App\Support\Experience\ExperienceService::class, 'activate'));
        $this->assertTrue(method_exists(\App\Support\Experience\ExperienceService::class, 'switch'));
        $user = User::factory()->withoutExperience()->create();
        $workspace = $user->primaryWorkspace();
        $service = app(\App\Support\Experience\ExperienceService::class);
        $service->selectInitial($user, Experience::LEARNING);

        $service->activate($user->fresh(), Experience::BUSINESS);
        $service->switch($user->fresh(), Experience::BUSINESS);

        $user->refresh();
        $this->assertSame('learning', $user->initial_experience?->value);
        $this->assertSame('business', $user->active_experience?->value);
        $this->assertTrue($user->hasBusinessExperience());
        $this->assertTrue($user->hasLearningExperience());
        $this->assertSame($workspace->id, $user->primaryWorkspace()->id);
        $this->assertCount(1, $user->workspaces);
    }
}
