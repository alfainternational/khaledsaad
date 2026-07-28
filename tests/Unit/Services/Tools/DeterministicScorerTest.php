<?php

namespace Tests\Unit\Services\Tools;

use App\Models\ToolVersion;
use App\Modules\Diagnosis\DeterministicScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeterministicScorerTest extends TestCase
{
    private function version(array $rules): ToolVersion
    {
        return new ToolVersion(['scoring_rules' => ['rules' => $rules]]);
    }

    #[Test]
    public function it_returns_the_same_score_for_the_same_answers(): void
    {
        $version = $this->version([
            ['field' => 'tracking', 'type' => 'map', 'weight' => 20, 'map' => ['none' => 0, 'full' => 1]],
            ['field' => 'offer', 'type' => 'present', 'weight' => 10],
        ]);

        $scorer = new DeterministicScorer;
        $answers = ['tracking' => 'full', 'offer' => 'وعد واضح'];

        $first = $scorer->score($version, $answers);
        $second = $scorer->score($version, $answers);

        // ثبات الدرجة هو ما يجعل المقارنة الزمنية ذات معنى.
        $this->assertSame($first['score'], $second['score']);
        $this->assertSame(100, $first['score']);
    }

    #[Test]
    public function it_scores_zero_when_nothing_is_answered(): void
    {
        $version = $this->version([
            ['field' => 'tracking', 'type' => 'map', 'weight' => 20, 'map' => ['none' => 0, 'full' => 1]],
            ['field' => 'offer', 'type' => 'present', 'weight' => 10],
        ]);

        $result = (new DeterministicScorer)->score($version, ['tracking' => 'none', 'offer' => '']);

        $this->assertSame(0, $result['score']);
        $this->assertSame('مبعثر', $result['band']);
    }

    #[Test]
    public function it_weights_partial_answers_proportionally(): void
    {
        $version = $this->version([
            ['field' => 'channels', 'type' => 'count', 'target' => 4, 'weight' => 100],
        ]);

        $result = (new DeterministicScorer)->score($version, ['channels' => ['seo', 'paid']]);

        $this->assertSame(50, $result['score']);
    }

    #[Test]
    public function it_exposes_a_breakdown_for_every_rule(): void
    {
        $version = $this->version([
            ['field' => 'offer', 'label' => 'وضوح العرض', 'type' => 'present', 'weight' => 10],
        ]);

        $result = (new DeterministicScorer)->score($version, ['offer' => 'نعم']);

        $this->assertCount(1, $result['breakdown']);
        $this->assertSame('وضوح العرض', $result['breakdown'][0]['label']);
    }
}
