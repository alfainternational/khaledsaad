<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Ops\AnomalyDetector;
use App\Domain\AI\Kernel\Agents\Ops\ChangeDetector;
use App\Domain\AI\Kernel\Agents\Ops\ExecutionGate;
use App\Domain\AI\Kernel\Agents\Ops\PortfolioHealthScorer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpsCapabilitiesTest extends TestCase
{
    #[Test]
    public function anomaly_detector_flags_a_spike(): void
    {
        $r = (new AnomalyDetector)->detect([10, 11, 9, 10, 12, 10, 90]);

        $this->assertSame('anomaly', $r['status']);
        $this->assertNotEmpty($r['anomalies']);
        $this->assertSame(6, $r['anomalies'][0]['index']);
    }

    #[Test]
    public function anomaly_detector_reports_normal_stable_series(): void
    {
        $r = (new AnomalyDetector)->detect([10, 10, 11, 9, 10, 10]);

        $this->assertSame('normal', $r['status']);
        $this->assertSame([], $r['anomalies']);
    }

    #[Test]
    public function anomaly_detector_handles_insufficient_data(): void
    {
        $this->assertSame('insufficient_data', (new AnomalyDetector)->detect([1, 2])['status']);
    }

    #[Test]
    public function change_detector_reports_added_removed_and_changed(): void
    {
        $r = (new ChangeDetector)->diff(
            ['price' => '100', 'plan' => 'pro', 'gone' => 'x'],
            ['price' => '120', 'plan' => 'pro', 'newkey' => 'y'],
        );

        $this->assertTrue($r['changed']);
        $types = collect($r['changes'])->pluck('type', 'key');
        $this->assertSame('changed', $types['price']);
        $this->assertSame('added', $types['newkey']);
        $this->assertSame('removed', $types['gone']);
    }

    #[Test]
    public function change_detector_reports_no_change_on_identical(): void
    {
        $r = (new ChangeDetector)->diff(['a' => 1], ['a' => 1]);

        $this->assertFalse($r['changed']);
        $this->assertSame(0, $r['count']);
    }

    #[Test]
    public function portfolio_scorer_computes_rag_and_overall(): void
    {
        $r = (new PortfolioHealthScorer)->score([
            ['name' => 'عميل أ', 'signals' => [90, 80, 85]],   // green
            ['name' => 'عميل ب', 'signals' => [60, 55, 50]],   // amber
            ['name' => 'عميل ج', 'signals' => [30, 40, 20]],   // red
        ]);

        $this->assertSame('green', $r['clients'][0]['rag']);
        $this->assertSame('amber', $r['clients'][1]['rag']);
        $this->assertSame('red', $r['clients'][2]['rag']);
        $this->assertSame(['green' => 1, 'amber' => 1, 'red' => 1], $r['distribution']);
        $this->assertIsInt($r['overall']);
    }

    #[Test]
    public function execution_gate_always_requires_approval_and_never_auto_executes(): void
    {
        $r = (new ExecutionGate)->assess('محتوى جاهز للنشر', ['budget' => 100, 'spent' => 50]);

        $this->assertTrue($r['approval_required']);
        $this->assertFalse($r['can_execute']);
        $this->assertTrue($r['budget_ok']);
    }

    #[Test]
    public function execution_gate_blocks_on_budget_overrun_and_empty_content(): void
    {
        $r = (new ExecutionGate)->assess('', ['budget' => 100, 'spent' => 150]);

        $this->assertFalse($r['content_present']);
        $this->assertFalse($r['budget_ok']);
        $this->assertContains('تجاوز سقف الميزانية المسموح.', $r['blockers']);
    }
}
