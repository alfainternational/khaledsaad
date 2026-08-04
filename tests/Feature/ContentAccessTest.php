<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Services\Content\ContentAccessService;
use App\Services\Content\ContentSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_content_never_requires_a_subscriber(): void
    {
        $content = new Content(['access_level' => Content::ACCESS_PUBLIC]);

        $this->assertTrue(app(ContentAccessService::class)->canView($content));
    }

    public function test_subscriber_content_requires_a_valid_opaque_token(): void
    {
        $content = new Content(['access_level' => Content::ACCESS_SUBSCRIBERS]);
        $subscriptions = app(ContentSubscriptionService::class);
        $access = app(ContentAccessService::class);

        $this->assertFalse($access->canView($content));
        $this->assertFalse($access->canView($content, 'invalid-token'));

        $result = $subscriptions->subscribe(' Reader@Example.COM ', true);

        $this->assertSame('reader@example.com', $result['subscriber']->email);
        $this->assertTrue($access->canView($content, $result['token']));
        $this->assertNotSame($result['token'], $result['subscriber']->access_token_hash);
    }

    public function test_subscription_requires_explicit_consent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ContentSubscriptionService::class)->subscribe('reader@example.com', false);
    }
}
