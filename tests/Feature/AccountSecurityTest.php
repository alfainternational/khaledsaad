<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_security_page_shows_sessions_and_toggles_email_otp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.security'))
            ->assertOk()
            ->assertSee('خطوة تحقق ثانية بالبريد');

        $this->actingAs($user)->post(route('app.security.otp'))->assertRedirect();
        $this->assertTrue($user->fresh()->two_factor_email_enabled);

        $this->actingAs($user)->post(route('app.security.otp'))->assertRedirect();
        $this->assertFalse($user->fresh()->two_factor_email_enabled);
    }

    #[Test]
    public function a_user_with_email_otp_cannot_login_with_password_alone(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
            'two_factor_email_enabled' => true,
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('login.otp'));

        // كلمة المرور وحدها لا تدخل، والرمز أُرسل بالبريد.
        $this->assertGuest();
        Notification::assertSentTo($user, LoginOtpNotification::class);

        // الرمز الصحيح يكمل الدخول.
        cache()->put('login-otp:'.$user->id, Hash::make('123456'), now()->addMinutes(10));

        $this->post(route('login.otp.verify'), ['code' => '123456'])
            ->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function the_otp_screen_renders_its_form(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
            'two_factor_email_enabled' => true,
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('login.otp'));

        // الشاشة تُطلب كما يطلبها المتصفح: من فقد النموذج هنا فقد الدخول كله.
        $response = $this->get(route('login.otp'))->assertOk();

        $response->assertSee('أدخل رمز الدخول');
        $response->assertSee('>الرمز</label>', false);
        $response->assertSee('name="code"', false);
        $response->assertSee('action="'.route('login.otp.verify').'"', false);
    }

    #[Test]
    public function a_wrong_code_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
            'two_factor_email_enabled' => true,
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-password']);
        cache()->put('login-otp:'.$user->id, Hash::make('123456'), now()->addMinutes(10));

        $this->post(route('login.otp.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }
}
