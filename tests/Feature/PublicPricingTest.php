<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicPricingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_public_pricing_page_lists_public_plans_from_the_database(): void
    {
        Plan::create(['key' => 'free', 'name' => 'المستكشف', 'interval' => 'monthly', 'price' => 0, 'monthly_credits' => 3, 'project_limit' => 1, 'is_public' => true, 'sort_order' => 1]);
        Plan::create(['key' => 'pro', 'name' => 'المنفّذ', 'interval' => 'monthly', 'price' => 149, 'monthly_credits' => 30, 'project_limit' => 5, 'is_public' => true, 'sort_order' => 2]);
        Plan::create(['key' => 'hidden', 'name' => 'خطة داخلية', 'interval' => 'monthly', 'price' => 999, 'monthly_credits' => 99, 'project_limit' => 99, 'is_public' => false, 'sort_order' => 3]);

        $this->get(route('pricing'))
            ->assertOk()
            ->assertSee('المستكشف')
            ->assertSee('المنفّذ')
            ->assertDontSee('خطة داخلية')
            ->assertSee('مجانًا')
            ->assertSee('FAQPage', false);
    }

    #[Test]
    public function the_sitemap_lists_public_pages_and_tools(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('pricing'), false)
            ->assertSee(route('tools.index'), false);
    }
}
