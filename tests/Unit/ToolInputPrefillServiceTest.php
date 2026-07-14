<?php

namespace Tests\Unit;

use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Support\Tooling\ToolInputPrefillService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolInputPrefillServiceTest extends TestCase
{
    private ToolInputPrefillService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ToolInputPrefillService;
    }

    private function tool(string $code): Tool
    {
        $tool = new Tool;
        $tool->code = $code;

        return $tool;
    }

    /** @param array<string, mixed> $inputs */
    private function makeRun(string $toolCode, array $inputs): ToolRun
    {
        $run = new ToolRun;
        $run->tool_code = $toolCode;
        $run->inputs_json = $inputs;

        return $run;
    }

    #[Test]
    public function it_prefills_fields_from_the_previous_run_of_the_same_tool(): void
    {
        $result = $this->service->suggest(
            $this->tool('diagnosis'),
            ['main_goal', 'main_bottleneck', 'needed_outcome'],
            $this->makeRun('diagnosis', [
                'main_goal' => 'أول 10 عملاء',
                'main_bottleneck' => 'رسالتي غير واضحة',
            ]),
            null,
        );

        $this->assertSame('أول 10 عملاء', $result['main_goal']['value']);
        $this->assertSame('previous_run', $result['main_goal']['source']);
        $this->assertSame('من إجابتك السابقة', $result['main_goal']['label']);
        $this->assertSame('رسالتي غير واضحة', $result['main_bottleneck']['value']);
        // حقل لم يُجَب سابقاً لا يُقترح له شيء.
        $this->assertArrayNotHasKey('needed_outcome', $result);
    }

    #[Test]
    public function it_ignores_a_previous_run_from_a_different_tool(): void
    {
        $result = $this->service->suggest(
            $this->tool('offer-builder'),
            ['offer_name', 'offer_result'],
            $this->makeRun('diagnosis', ['main_goal' => 'أول 10 عملاء']),
            null,
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_skips_blank_previous_answers(): void
    {
        $result = $this->service->suggest(
            $this->tool('diagnosis'),
            ['main_goal', 'main_bottleneck'],
            $this->makeRun('diagnosis', ['main_goal' => '   ', 'main_bottleneck' => 'العائق الحقيقي']),
            null,
        );

        $this->assertArrayNotHasKey('main_goal', $result);
        $this->assertSame('العائق الحقيقي', $result['main_bottleneck']['value']);
    }

    #[Test]
    public function it_returns_nothing_without_a_previous_run_or_project(): void
    {
        $result = $this->service->suggest(
            $this->tool('diagnosis'),
            ['main_goal'],
            null,
            null,
        );

        $this->assertSame([], $result);
    }
}
