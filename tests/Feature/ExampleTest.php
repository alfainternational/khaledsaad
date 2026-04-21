<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_renders_the_platform_identity(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('منصة التسويق الاستراتيجي')
            ->assertSee('رتّب تسويق مشروعك');
    }
}
