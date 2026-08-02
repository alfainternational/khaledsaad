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

    #[Test]
    public function it_exposes_each_rules_share_of_the_activated_total(): void
    {
        $version = $this->version([
            ['field' => 'objective', 'label' => 'هدف محدد', 'type' => 'present', 'weight' => 10],
            ['field' => 'tracking', 'label' => 'تتبع', 'type' => 'map', 'weight' => 30, 'map' => ['none' => 0, 'full' => 1]],
        ]);

        $result = (new DeterministicScorer)->score($version, ['objective' => 'بيع', 'tracking' => 'none']);

        // الوزن وحده لا يقول شيئًا: 10 من أصل 40 تعني ربع الدرجة لا عشرها.
        $this->assertSame(40.0, $result['total_weight']);
        $this->assertSame(25.0, $result['breakdown'][0]['share']);
        $this->assertSame(75.0, $result['breakdown'][1]['share']);
    }

    #[Test]
    public function the_share_of_the_same_rule_changes_with_the_activated_set(): void
    {
        $version = $this->version([
            ['field' => 'objective', 'label' => 'هدف محدد', 'type' => 'present', 'weight' => 10],
            ['field' => 'local', 'label' => 'نطاق محلي', 'type' => 'present', 'weight' => 30],
        ]);

        $scorer = new DeterministicScorer;

        $wide = $scorer->score($version, ['objective' => 'بيع', 'local' => 'الرياض']);
        $narrow = $scorer->score($version, ['objective' => 'بيع'], ['objective']);

        // نفس البند بنفس الإجابة يساوي حصتين مختلفتين — وهذا بالضبط ما يجب أن يراه المستخدم.
        $this->assertSame(25.0, $wide['breakdown'][0]['share']);
        $this->assertSame(100.0, $narrow['breakdown'][0]['share']);
    }

    #[Test]
    public function it_reports_rules_excluded_by_project_context(): void
    {
        $version = $this->version([
            ['field' => 'objective', 'label' => 'هدف محدد', 'type' => 'present', 'weight' => 10],
            ['field' => 'service_radius', 'label' => 'النطاق الجغرافي', 'type' => 'present', 'weight' => 12],
        ]);

        $result = (new DeterministicScorer)->score($version, ['objective' => 'بيع'], ['objective']);

        // الفجوة تُعلن (§٤.٣): البند المستبعد يُذكر بدل أن يختفي من القاسم صامتًا.
        $this->assertCount(1, $result['excluded']);
        $this->assertSame('النطاق الجغرافي', $result['excluded'][0]['label']);
        $this->assertSame(12.0, $result['excluded'][0]['weight']);
    }

    #[Test]
    public function it_publishes_the_scale_behind_a_mapped_answer(): void
    {
        $version = $this->version([
            ['field' => 'unit_economics', 'label' => 'اقتصاديات الوحدة', 'type' => 'map', 'weight' => 20,
                'map' => ['unknown' => 0, 'rough' => 0.5, 'known' => 1]],
        ]);

        $result = (new DeterministicScorer)->score($version, ['unit_economics' => 'rough']);

        // من يرى «10 / 20» بلا سلّم لا يعرف ما الإجابة التي كانت ترفعه إلى 20.
        $this->assertSame('rough', $result['breakdown'][0]['value']);
        $this->assertSame(10.0, $result['breakdown'][0]['points']);
        $this->assertSame(
            [['key' => 'unknown', 'factor' => 0.0], ['key' => 'rough', 'factor' => 0.5], ['key' => 'known', 'factor' => 1.0]],
            $result['breakdown'][0]['scale'],
        );
    }
}
