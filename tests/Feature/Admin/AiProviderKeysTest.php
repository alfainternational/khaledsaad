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
            ->patch(route('admin.ai-control.providers'), $this->payload([
                'gemini_key' => 'AIza-NEW-GEMINI-KEY',
                'nvidia_key' => 'nvapi-NEW-KEY',
                'nvidia_model' => 'meta/llama-3.1-8b-instruct',
                'nvidia_max_tokens' => 2048,
                'nvidia_timeout' => 30,
                'gemini_timeout' => 40,
                'groq_key' => 'gsk-NEW-GROQ',
                'chain' => 'groq,cerebras,nvidia',
            ]))
            ->assertRedirect();

        $settings = app(SettingsStore::class);
        $this->assertSame('AIza-NEW-GEMINI-KEY', $settings->get('services.gemini.key'));
        $this->assertSame('nvapi-NEW-KEY', $settings->get('services.nvidia.key'));
        $this->assertSame('meta/llama-3.1-8b-instruct', $settings->get('services.nvidia.model'));
        $this->assertSame(2048, $settings->get('services.nvidia.max_tokens'));
        $this->assertSame(30, $settings->get('services.nvidia.timeout'));
        $this->assertSame(40, $settings->get('services.gemini.timeout'));
        $this->assertSame('gsk-NEW-GROQ', $settings->get('services.ai.providers.groq.key'));
        $this->assertSame('groq,cerebras,nvidia', $settings->get('services.ai.chain'));
    }

    #[Test]
    public function chain_is_sanitized_to_known_providers_only(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.ai-control.providers'), $this->payload([
                'chain' => 'groq, evil-provider , nvidia, groq',
            ]))
            ->assertRedirect();

        // أسماء معروفة فقط، بلا تكرار، مرتّبة.
        $this->assertSame('groq,nvidia', app(SettingsStore::class)->get('services.ai.chain'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'chain' => 'groq,cerebras,nvidia',
            'gemini_key' => '',
            'gemini_model' => 'gemini-2.5-flash',
            'gemini_timeout' => 45,
            'nvidia_key' => '',
            'nvidia_model' => 'meta/llama-3.1-70b-instruct',
            'nvidia_max_tokens' => 2048,
            'nvidia_timeout' => 45,
            'groq_key' => '',
            'groq_model' => 'llama-3.3-70b-versatile',
            'cerebras_key' => '',
            'cerebras_model' => 'llama-3.3-70b',
            'openrouter_key' => '',
            'openrouter_model' => 'meta-llama/llama-3.3-70b-instruct:free',
        ], $overrides);
    }

    #[Test]
    public function blank_key_keeps_the_existing_secret(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();
        app(SettingsStore::class)->set('services.gemini.key', 'EXISTING-KEY');

        $this->actingAs($admin)
            ->patch(route('admin.ai-control.providers'), $this->payload(['gemini_key' => ''])) // فارغ = إبقاء
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

        $this->actingAs($admin)->patch(
            route('admin.ai-control.providers'),
            $this->payload(['gemini_key' => 'TOP-SECRET-VALUE']),
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.ai_control.providers_updated']);
        $this->assertDatabaseMissing('audit_logs', ['meta' => 'TOP-SECRET-VALUE']);
        // تأكيد أدقّ: القيمة السرّية غير موجودة في أي سجلّ تدقيق.
        $leaked = \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('meta', 'like', '%TOP-SECRET-VALUE%')->exists();
        $this->assertFalse($leaked, 'يجب ألّا تُسجَّل قيمة المفتاح في التدقيق');
    }
}
