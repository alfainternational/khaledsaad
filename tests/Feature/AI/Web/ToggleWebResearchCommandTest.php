<?php

namespace Tests\Feature\AI\Web;

use App\Providers\AppServiceProvider;
use App\Support\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToggleWebResearchCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_research_and_refresh_state_across_web_processes(): void
    {
        Storage::fake('local');
        config()->set('services.web_search.verified_research', false);
        config()->set('services.web_search.scheduled_refresh', false);

        $this->artisan('web-research:toggle', ['state' => 'on', '--refresh' => 'on', '--json' => true])
            ->expectsOutput('{"enabled":true,"refresh_enabled":true}')
            ->assertSuccessful();

        config()->set('services.web_search.verified_research', false);
        config()->set('services.web_search.scheduled_refresh', false);
        (new AppServiceProvider(app()))->boot();

        $this->assertTrue((bool) config('services.web_search.verified_research'));
        $this->assertTrue((bool) config('services.web_search.scheduled_refresh'));
        $this->assertTrue(app(SettingsStore::class)->get('services.web_search.verified_research'));
        $this->assertTrue(app(SettingsStore::class)->get('services.web_search.scheduled_refresh'));

        $this->artisan('web-research:toggle', ['state' => 'off', '--refresh' => 'off'])->assertSuccessful();
        $this->assertFalse((bool) config('services.web_search.verified_research'));
        $this->assertFalse((bool) config('services.web_search.scheduled_refresh'));
    }

    #[Test]
    public function it_rejects_invalid_states_without_persisting_them(): void
    {
        Storage::fake('local');

        $this->artisan('web-research:toggle', ['state' => 'maybe'])->assertExitCode(2);

        $this->assertNull(app(SettingsStore::class)->get('services.web_search.verified_research'));
    }
}
