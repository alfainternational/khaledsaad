<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingRevealFallbackTest extends TestCase
{
    public function test_homepage_includes_reveal_visibility_fallback(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-reveal-fallback', false);
    }
}
