<?php

namespace Tests\Feature\Admin;

use App\Domain\AI\Models\AITemplate;
use App\Domain\Billing\Models\Plan;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\Tool\Models\Tool;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function platform_bootstrap_seeder_creates_default_admin_plans_and_flags(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $this->assertDatabaseCount('plans', 6);
        $this->assertDatabaseHas('users', [
            'email' => config('platform.admin.email'),
            'is_super_admin' => true,
        ]);
        $this->assertSame(6, Plan::query()->count());
        $this->assertSame(4, FeatureFlag::query()->count());
        $this->assertSame(26, Tool::query()->count());
        $this->assertSame(10, AITemplate::query()->count());
    }

    #[Test]
    public function running_the_seeder_twice_does_not_duplicate_the_super_admin(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $this->seed(PlatformBootstrapSeeder::class);

        $this->assertSame(1, User::query()->where('email', config('platform.admin.email'))->count());
    }

    #[Test]
    public function studio_templates_are_seeded_with_execution_ready_contracts(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $socialAd = AITemplate::query()->where('code', 'social-ad')->firstOrFail();
        $contentCalendar = AITemplate::query()->where('code', 'content-calendar')->firstOrFail();
        $brandPositioning = AITemplate::query()->where('code', 'brand-positioning')->firstOrFail();
        $brandFullPack = AITemplate::query()->where('code', 'brand-full-pack')->firstOrFail();

        $this->assertStringContainsString('4 نسخ إعلانية كاملة', $socialAd->prompt_template);
        $this->assertStringContainsString('مدير الإعلانات', $socialAd->system_role);
        $this->assertGreaterThanOrEqual(6, count($socialAd->output_contract_json['sections'] ?? []));
        $this->assertStringContainsString('CTA', $socialAd->output_contract_json['quality_rubric'] ?? '');

        $this->assertStringContainsString('Hook', $contentCalendar->prompt_template);
        $this->assertStringContainsString('إعادة استخدام', implode(' | ', $contentCalendar->output_contract_json['sections'] ?? []));

        $this->assertStringContainsString('Unique Mechanism', $brandPositioning->prompt_template);
        $this->assertStringContainsString('رسالة بيع افتتاحية', implode(' | ', $brandPositioning->output_contract_json['sections'] ?? []));
        $this->assertStringContainsString('Framework', $brandPositioning->output_contract_json['quality_rubric'] ?? '');

        $this->assertStringContainsString('رسائل جاهزة للاستخدام', implode(' | ', $brandFullPack->output_contract_json['sections'] ?? []));
        $this->assertStringContainsString('Boundary', implode(' | ', $brandFullPack->output_contract_json['sections'] ?? []));
        $this->assertStringContainsString('صاحب قرار', $brandFullPack->system_role);
        $this->assertStringContainsString('30–60–90', $brandFullPack->prompt_template);
    }
}
