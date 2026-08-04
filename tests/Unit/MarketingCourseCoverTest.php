<?php

namespace Tests\Unit;

use Tests\TestCase;

class MarketingCourseCoverTest extends TestCase
{
    public function test_every_lesson_has_source_hero_card_and_open_graph_images(): void
    {
        foreach (range(1, 20) as $order) {
            $stem = sprintf('lesson-%02d', $order);
            $source = public_path("assets/content/marketing-course/source/{$stem}.png");
            $hero = public_path("assets/content/marketing-course/{$stem}-hero.webp");
            $card = public_path("assets/content/marketing-course/{$stem}-card.webp");
            $og = public_path("assets/content/marketing-course/{$stem}-og.png");

            $this->assertFileExists($source);
            $this->assertFileExists($hero);
            $this->assertFileExists($card);
            $this->assertFileExists($og);
            $this->assertSame([1600, 900], array_slice(getimagesize($hero), 0, 2));
            $this->assertSame([800, 450], array_slice(getimagesize($card), 0, 2));
            $this->assertSame([1200, 630], array_slice(getimagesize($og), 0, 2));
            $this->assertGreaterThan(10_000, filesize($og));
        }
    }
}
