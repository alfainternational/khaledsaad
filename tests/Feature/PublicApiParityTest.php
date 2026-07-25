<?php

namespace Tests\Feature;

use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
