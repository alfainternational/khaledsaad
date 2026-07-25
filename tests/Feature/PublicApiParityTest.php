<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicApiParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function public_bootstrap_exposes_the_same_brand_and_tool_entry_data_as_the_web(): void
    {
        $this->assertTrue(Route::has('api.v1.public.bootstrap'));

        $response = $this->getJson(route('api.v1.public.bootstrap'))
            ->assertOk()
            ->assertJsonPath('data.brand.name', 'خالد سعد')
            ->assertJsonPath('data.brand.contact.whatsapp', 'https://wa.me/966533052074')
            ->assertJsonCount(11, 'data.tools')
            ->assertJsonStructure([
                'data' => [
                    'brand' => [
                        'name',
                        'tagline',
                        'headline',
                        'location',
                        'experience_years',
                        'about',
                        'contact',
                        'services',
                        'problems',
                        'method',
                        'experience',
                        'education',
                        'credentials',
                        'skills',
                        'knowledge',
                        'faqs',
                    ],
                    'tools',
                    'tool_stats',
                    'entry_tool',
                    'links' => ['privacy', 'terms'],
                ],
            ]);

        $this->assertSame(
            config('brand.tagline'),
            $response->json('data.brand.tagline'),
        );
    }

    #[Test]
    public function public_legal_contract_uses_the_same_configuration_as_the_web(): void
    {
        $this->assertTrue(Route::has('api.v1.public.legal'));

        foreach (['privacy', 'terms'] as $page) {
            $this->getJson(route('api.v1.public.legal', $page))
                ->assertOk()
                ->assertJsonPath('data.slug', $page)
                ->assertJsonPath('data.title', config("legal.{$page}.title"))
                ->assertJsonPath('data.intro', config("legal.{$page}.intro"))
                ->assertJsonPath('data.sections', config("legal.{$page}.sections"));
        }

        $this->getJson(route('api.v1.public.legal', 'unknown'))->assertNotFound();
    }

    #[Test]
    public function password_reset_requests_do_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson(route('api.v1.auth.forgot-password'), [
            'email' => $user->email,
        ])->assertOk()->json('data.message');

        $unknown = $this->postJson(route('api.v1.auth.forgot-password'), [
            'email' => 'unknown@example.com',
        ])->assertOk()->json('data.message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function a_valid_api_reset_token_changes_the_password_and_an_invalid_token_is_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = null;

        $this->postJson(route('api.v1.auth.forgot-password'), ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertIsString($token);

        $this->postJson(route('api.v1.auth.reset-password'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'New-secure-password-2026',
            'password_confirmation' => 'New-secure-password-2026',
        ])->assertOk()->assertJsonPath('data.message', 'تغيّرت كلمة المرور. يمكنك تسجيل الدخول الآن.');

        $this->assertTrue(Hash::check('New-secure-password-2026', $user->fresh()->password));

        $this->postJson(route('api.v1.auth.reset-password'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'Another-secure-password-2026',
            'password_confirmation' => 'Another-secure-password-2026',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    #[Test]
    public function authentication_payload_exposes_the_server_authoritative_admin_role(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password-2026',
            'is_admin' => true,
        ]);

        $this->postJson(route('api.v1.auth.login'), [
            'email' => $admin->email,
            'password' => 'password-2026',
            'device_name' => 'test-device',
        ])->assertOk()->assertJsonPath('data.user.is_admin', true);
    }
}
