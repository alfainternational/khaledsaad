<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_renders_the_platform_identity(): void
    {
        $response = $this->get('/');

        // Assert stable structure (not volatile marketing copy) so the test survives
        // ongoing content edits: the hero, the nav, and the wired diagnosis funnel CTA.
        $response
            ->assertOk()
            ->assertSee('hero-section')
            ->assertSee('site-nav')
            ->assertSee('/diagnose');
    }
}
