<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Settings\SettingsStore;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiProviderKeysTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_set_provider_keys_models_and_speed(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.ai-control.providers'), [
                'gemini_key' => 'AIza-NEW-GEMINI-KEY',
                'gemini_model' => 'gemini-2.5-flash',
                'gemini_timeout' => 40,
                'nvidia_key' => 'nvapi-NEW-KEY',
                'nvidia_model' => 'meta/llama-3.1-8b-instruct',
                'nvidia_max_tokens' => 2048,
                'nvidia_timeout' => 30,
            ])
            ->assertRedirect();

        $settings = app(SettingsStore::class);
        $this->assertSame('AIza-NEW-GEMINI-KEY', $settings->get('services.gemini.key'));
        $this->assertSame('nvapi-NEW-KEY', $settings->get('services.nvidia.key'));
        $this->assertSame('meta/llama-3.1-8b-instruct', $settings->get('services.nvidia.model'));
        $this->assertSame(2048, $settings->get('services.nvidia.max_tokens'));
        $this->assertSame(30, $settings->get('services.nvidia.timeout'));
        $this->assertSame(40, $settings->get('services.gemini.timeout'));
    }

    #[Test]
    public function blank_key_keeps_the_existing_secret(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        app(SettingsStore::class)->set('services.gemini.key', 'EXISTING-KEY');

        $this->actingAs($admin)
            ->patch(route('admin.ai-control.providers'), [
                'gemini_key' => '', // فارغ = إبقاء
                'gemini_model' => 'gemini-2.5-flash',
                'gemini_timeout' => 45,
                'nvidia_key' => '',
                'nvidia_model' => 'meta/llama-3.1-70b-instruct',
                'nvidia_max_tokens' => 4096,
                'nvidia_timeout' => 45,
            ])
            ->assertRedirect();

        $this->assertSame('EXISTING-KEY', app(SettingsStore::class)->get('services.gemini.key'));
    }

    #[Test]
    public function control_page_masks_keys_and_never_leaks_them(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        // الإخفاء يقرأ من config (يُملأ من SettingsStore عند الإقلاع في الإنتاج).
        config(['services.gemini.key' => 'SECRET-abcd1234']);

        $response = $this->actingAs($admin)->get(route('admin.ai-control.index'));

        $response->assertOk();
        $response->assertSee('••••1234');           // مقنّع
        $response->assertDontSee('SECRET-abcd1234'); // لا يُكشف الخام أبداً
    }

    #[Test]
    public function audit_log_records_change_without_the_secret_value(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.ai-control.providers'), [
            'gemini_key' => 'TOP-SECRET-VALUE',
            'gemini_model' => 'gemini-2.5-flash',
            'gemini_timeout' => 45,
            'nvidia_key' => '',
            'nvidia_model' => 'meta/llama-3.1-70b-instruct',
            'nvidia_max_tokens' => 4096,
            'nvidia_timeout' => 45,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.ai_control.providers_updated']);
        $this->assertDatabaseMissing('audit_logs', ['meta' => 'TOP-SECRET-VALUE']);
        // تأكيد أدقّ: القيمة السرّية غير موجودة في أي سجلّ تدقيق.
        $leaked = \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('meta', 'like', '%TOP-SECRET-VALUE%')->exists();
        $this->assertFalse($leaked, 'يجب ألّا تُسجَّل قيمة المفتاح في التدقيق');
    }
}
