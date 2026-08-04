<?php

namespace Tests\Feature;

use App\Models\GuestSession;
use App\Models\ToolRun;
use App\Models\User;
use App\Models\Workspace;
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
                        'professional_headline',
                        'about',
                        'contact',
                        'services',
                        'problems',
                        'method',
                        'experience',
                        'education',
                        'credentials',
                        'skills',
                        'professional_services',
                        'principles',
                        'knowledge',
                        'faqs',
                    ],
                    'tools',
                    'tool_stats',
                    'entry_tool',
                    'links' => [
                        'privacy',
                        'terms',
                        'profile',
                        'profile_pdf',
                        'services',
                        'methodology',
                        'knowledge',
                        'faq',
                    ],
                ],
            ]);

        $this->assertSame(
            config('brand.tagline'),
            $response->json('data.brand.tagline'),
        );
        $this->assertSame(route('profile'), $response->json('data.links.profile'));
        $this->assertSame(route('profile.pdf'), $response->json('data.links.profile_pdf'));
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

    #[Test]
    public function a_mobile_visitor_can_start_resume_and_save_a_guest_run_with_an_opaque_token(): void
    {
        $start = $this->postJson(route('api.v1.public.runs.start', 'marketing-score'))
            ->assertCreated()
            ->assertJsonPath('data.session_created', true)
            ->assertJsonPath('data.run.tool.key', 'marketing-score');

        $token = $start->json('data.guest_token');
        $uuid = $start->json('data.run.uuid');

        $this->assertIsString($token);
        $this->assertSame(48, strlen($token));
        $this->assertNotSame($token, GuestSession::firstOrFail()->token_hash);

        $this->withHeader('X-Guest-Token', $token)
            ->getJson(route('api.v1.public.runs.show', $uuid))
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->withHeader('X-Guest-Token', $token)
            ->putJson(route('api.v1.public.runs.step', [$uuid, 1]), [
                'business_model' => 'services',
                'description' => 'خدمة استشارات تسويقية للمتاجر الصغيرة داخل المدينة.',
                'geography' => 'الرياض',
            ])
            ->assertOk()
            ->assertJsonPath('data.answers.geography', 'الرياض');
    }

    #[Test]
    public function a_guest_token_cannot_open_another_visitors_run(): void
    {
        $first = $this->postJson(route('api.v1.public.runs.start', 'marketing-score'));
        $firstUuid = $first->json('data.run.uuid');

        $second = $this->postJson(route('api.v1.public.runs.start', 'marketing-score'), [], [
            'X-Guest-Token' => 'invalid-existing-token',
        ]);
        $secondToken = $second->json('data.guest_token');

        $this->withHeader('X-Guest-Token', $secondToken)
            ->getJson(route('api.v1.public.runs.show', $firstUuid))
            ->assertNotFound();
    }

    #[Test]
    public function api_registration_claims_the_existing_guest_workspace_and_run(): void
    {
        $start = $this->postJson(route('api.v1.public.runs.start', 'marketing-score'));
        $token = $start->json('data.guest_token');
        $uuid = $start->json('data.run.uuid');

        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'مستخدم التطبيق',
            'email' => 'mobile-guest@example.com',
            'password' => 'password-2026',
            'device_name' => 'android-test',
            'guest_token' => $token,
        ])->assertCreated()
            ->assertJsonPath('data.claimed_run_uuid', $uuid)
            ->assertJsonPath('data.user.email', 'mobile-guest@example.com');

        $user = User::where('email', 'mobile-guest@example.com')->firstOrFail();
        $this->assertSame($user->id, Workspace::firstOrFail()->owner_id);
        $this->assertSame($user->id, GuestSession::firstOrFail()->claimed_by);
        $this->assertSame($user->id, ToolRun::where('uuid', $uuid)->firstOrFail()->user_id);
    }
}
