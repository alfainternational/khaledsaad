<?php

namespace Tests\Feature\AI\Worker;

use App\Providers\AppServiceProvider;
use App\Support\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TogglePrivateWorkerCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_and_applies_worker_state_without_environment_cache(): void
    {
        Storage::fake('local');
        config()->set('services.private_worker.enabled', false);

        $this->artisan('private-worker:toggle', ['state' => 'on', '--json' => true])
            ->expectsOutput('{"enabled":true}')
            ->assertSuccessful();

        $this->assertTrue((bool) config('services.private_worker.enabled'));
        $this->assertTrue(app(SettingsStore::class)->get('services.private_worker.enabled'));

        config()->set('services.private_worker.enabled', false);
        config()->set('services.ai.apply_settings_in_testing', true);
        (new AppServiceProvider(app()))->boot();
        $this->assertTrue((bool) config('services.private_worker.enabled'));

        $this->artisan('private-worker:toggle', ['state' => 'off'])->assertSuccessful();
        $this->assertFalse((bool) config('services.private_worker.enabled'));
        $this->assertFalse(app(SettingsStore::class)->get('services.private_worker.enabled'));
    }

    #[Test]
    public function it_rejects_unknown_states_without_changing_settings(): void
    {
        Storage::fake('local');

        $this->artisan('private-worker:toggle', ['state' => 'sometimes'])->assertExitCode(2);

        $this->assertNull(app(SettingsStore::class)->get('services.private_worker.enabled'));
    }

    #[Test]
    public function exclusive_mode_persists_the_local_provider_without_an_external_fallback(): void
    {
        Storage::fake('local');

        $this->artisan('private-worker:toggle', [
            'state' => 'on', '--exclusive' => true, '--wait' => 90, '--json' => true,
        ])->assertSuccessful();

        $settings = app(SettingsStore::class);
        $this->assertSame('private_worker', $settings->get('services.ai.provider'));
        $this->assertTrue($settings->get('services.private_worker.prefer_for_generation'));
        $this->assertSame(90, $settings->get('services.private_worker.gateway_wait_seconds'));
        $this->assertFalse($settings->get('services.ai.quality_judge'));
        $this->assertTrue($settings->get('services.ai.external_generation_disabled'));
        $this->assertSame('private_worker', config('services.ai.provider'));
    }
}
