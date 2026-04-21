<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingLegacyRoutesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function legacy_onboarding_paths_redirect_to_main_onboarding(): void
    {
        $user = User::factory()->create();

        $paths = [
            '/onboarding/context',
            '/onboarding/who-are-you',
            '/onboarding/your-goal',
            '/onboarding/suggested-path',
        ];

        foreach ($paths as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertRedirect('/onboarding');
        }
    }
}
