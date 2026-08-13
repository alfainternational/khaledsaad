<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_web_registration_requires_a_valid_experience(): void
    {
        $this->from(route('register'))
            ->post(route('register'), [
                'name' => 'مستخدم جديد',
                'email' => 'choice-required@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('experience');

        $this->assertDatabaseMissing('users', ['email' => 'choice-required@example.com']);
    }

    public function test_learning_registration_enters_learning_without_creating_a_project(): void
    {
        $this->post(route('register'), [
            'experience' => 'learning',
            'name' => 'متعلم جديد',
            'email' => 'learner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('app.learning.marketing.home'));

        $user = User::query()->where('email', 'learner@example.com')->sole();
        $this->assertSame('learning', $user->initial_experience?->value);
        $this->assertSame('learning', $user->active_experience?->value);
        $this->assertCount(1, $user->workspaces);
        $this->assertSame(0, $user->workspaces()->withCount('projects')->get()->sum('projects_count'));
    }

    public function test_business_registration_enters_the_first_project_journey(): void
    {
        $this->post(route('register'), [
            'experience' => 'business',
            'name' => 'صاحب مشروع',
            'email' => 'business@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('app.projects.create'));

        $user = User::query()->where('email', 'business@example.com')->sole();
        $this->assertTrue($user->hasBusinessExperience());
        $this->assertFalse($user->hasLearningExperience());
    }

    public function test_api_v1_registration_remains_backward_compatible_and_returns_experience_contract(): void
    {
        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'عميل قديم',
            'email' => 'legacy-mobile@example.com',
            'password' => 'password123',
            'device_name' => 'legacy-client',
        ])->assertCreated()
            ->assertJsonPath('data.user.initial_experience', 'business')
            ->assertJsonPath('data.user.active_experience', 'business')
            ->assertJsonPath('data.user.enabled_experiences.0', 'business')
            ->assertJsonPath('data.user.capabilities.can_activate_learning', true)
            ->assertJsonMissingPath('data.user.enabled_experiences.1');
    }

    public function test_auth_me_returns_the_same_experience_contract_without_creating_data(): void
    {
        $user = User::factory()->withoutExperience()->create();
        $workspace = $user->primaryWorkspace();
        $user = app(ExperienceService::class)->selectInitial($user, Experience::LEARNING);

        $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.auth.me'))
            ->assertOk()
            ->assertJsonPath('data.initial_experience', 'learning')
            ->assertJsonPath('data.active_experience', 'learning')
            ->assertJsonPath('data.enabled_experiences.0', 'learning')
            ->assertJsonPath('data.capabilities.can_activate_business', true);

        $this->assertCount(1, $user->fresh()->workspaces);
        $this->assertSame($workspace->id, $user->fresh()->primaryWorkspace()->id);
        $this->assertSame(0, $workspace->projects()->count());
    }

    public function test_learning_intent_returns_new_user_to_the_requested_application(): void
    {
        $target = '/app/learn/marketing/marketing-reality-check';

        $this->get(route('register', ['intent' => 'learning', 'return_url' => $target]))
            ->assertOk();

        $this->post(route('register'), [
            'experience' => 'learning',
            'name' => 'متعلم من رابط',
            'email' => 'learning-link@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect($target);

        $user = User::query()->where('email', 'learning-link@example.com')->sole();
        $this->assertTrue($user->hasLearningExperience());
        $this->assertFalse($user->hasBusinessExperience());
    }

    public function test_external_return_url_is_not_accepted_as_an_auth_redirect(): void
    {
        $this->get(route('register', [
            'intent' => 'learning',
            'return_url' => 'https://attacker.example/path',
        ]))->assertOk();

        $this->post(route('register'), [
            'experience' => 'learning',
            'name' => 'متعلم آمن',
            'email' => 'safe-link@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('app.learning.marketing.home'));
    }
}
