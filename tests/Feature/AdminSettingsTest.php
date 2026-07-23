<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Settings\SettingsConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * إدارة المفاتيح من اللوحة بدل .env: ما يحفظه الآدمن يسري حيًّا على config،
 * والأسرار تُخزَّن مشفّرة، والوصول محصور بالإدارة.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_settings_page_renders_the_key_catalog_for_an_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('الإعدادات والمفاتيح')
            ->assertSee('مفتاح DeepSeek')
            ->assertSee('تفعيل المصدر الحيّ');
    }

    #[Test]
    public function an_admin_saves_keys_and_they_apply_live_to_config(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'ai__deepseek__api_key' => 'sk-from-admin-panel',
                'ai__deepseek__base_url' => 'https://custom.deepseek.test',
                'benchmarks__live_enabled' => '1',
            ])
            ->assertRedirect(route('admin.settings'));

        // بعد التطبيق، يقرأ كل مستهلك القيم الجديدة من config مباشرة.
        app(SettingsConfig::class)->apply();

        $this->assertSame('sk-from-admin-panel', config('ai.deepseek.api_key'));
        $this->assertSame('https://custom.deepseek.test', config('ai.deepseek.base_url'));
        $this->assertTrue(config('benchmarks.live_enabled'));
    }

    #[Test]
    public function a_secret_is_stored_encrypted_not_in_plain_text(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'ai__deepseek__api_key' => 'sk-super-secret-value',
        ]);

        $stored = Setting::where('key', 'ai.deepseek.api_key')->value('value');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('sk-super-secret-value', $stored);
        // لكنها تُفك بشكل صحيح عند القراءة.
        $this->assertSame('sk-super-secret-value', Setting::get('ai.deepseek.api_key'));
    }

    #[Test]
    public function an_empty_text_override_reverts_to_the_env_default(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        config()->set('ai.deepseek.base_url', 'https://env-default.test');

        Setting::put('ai.deepseek.base_url', 'https://was-overridden.test', 'admin', 'string');
        $this->assertTrue(Setting::where('key', 'ai.deepseek.base_url')->exists());

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'ai__deepseek__base_url' => '',
        ]);

        // حذف التجاوز يعيد الاعتماد على .env.
        $this->assertFalse(Setting::where('key', 'ai.deepseek.base_url')->exists());
    }

    #[Test]
    public function a_non_admin_cannot_reach_the_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // 404 لا 403: اللوحة لا تُكشف لمن لا يملكها (سلوك EnsureUserIsAdmin).
        $this->actingAs($user)->get(route('admin.settings'))->assertNotFound();
        $this->actingAs($user)->put(route('admin.settings.update'), [])->assertNotFound();
    }
}
