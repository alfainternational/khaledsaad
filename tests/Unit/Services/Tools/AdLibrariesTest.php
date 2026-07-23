<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\AdLibraries;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * رؤية المنافسين: كل منصة تُعلن عليها تفتح نافذة على إعلانات منافسيك،
 * ومنصات المصدر الواحد تُدمج، وما لا مكتبة له يُصرّح به بصدق.
 */
class AdLibrariesTest extends TestCase
{
    #[Test]
    public function it_merges_platforms_that_share_one_ad_library(): void
    {
        $view = app(AdLibraries::class)->forPlatforms([
            'meta', 'whatsapp', 'google_search', 'youtube', 'google_shopping', 'tiktok',
        ]);

        $sources = collect($view)->pluck('source');

        // منصات Meta الأربع مصدرها واحد، ومنصات Google كذلك: لا تكرار.
        $this->assertSame($sources->unique()->count(), $sources->count());
        $this->assertTrue($sources->contains('مكتبة إعلانات Meta'));
        $this->assertTrue($sources->contains('مركز شفافية إعلانات Google'));

        $meta = collect($view)->firstWhere('source', 'مكتبة إعلانات Meta');
        $this->assertStringContainsString('فيسبوك', $meta['platforms']);
        $this->assertStringContainsString('واتساب', $meta['platforms']);
        $this->assertNotNull($meta['url']);
    }

    #[Test]
    public function it_is_honest_about_platforms_without_a_public_library(): void
    {
        $view = app(AdLibraries::class)->forPlatforms(['noon', 'amazon', 'x']);

        foreach ($view as $entry) {
            // بلا ادعاء: لا رابط مكتبة، ومعلَّم كمحدود، ويرشد إلى البديل.
            $this->assertNull($entry['url']);
            $this->assertTrue($entry['limited']);
            $this->assertNotEmpty($entry['what']);
        }
    }

    #[Test]
    public function unknown_platforms_are_ignored_not_faked(): void
    {
        $view = app(AdLibraries::class)->forPlatforms(['meta', 'nonexistent_platform']);

        $this->assertCount(1, $view);
    }
}
