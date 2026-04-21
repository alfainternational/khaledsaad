<?php

namespace Tests\Unit;

use App\Domain\AI\Models\AITemplate;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Support\AI\StudioTemplateContractRegistry;
use App\Support\AI\StudioTemplateReadinessGate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudioTemplateReadinessGateTest extends TestCase
{
    #[Test]
    public function it_blocks_generation_when_positioning_context_is_too_thin(): void
    {
        $gate = new StudioTemplateReadinessGate(new StudioTemplateContractRegistry);
        $template = new AITemplate(['code' => 'brand-positioning']);
        $workspace = new Workspace(['name' => 'Thin Workspace']);
        $project = new Project(['name' => 'Thin Project']);

        $assessment = $gate->assess($template, $workspace, $project, [
            'workspace_profile' => [
                'audience' => '',
                'primary_goal' => '',
            ],
            'tool_summaries' => [],
            'tool_runs' => [],
            'client_notes' => [],
            'approval_notes' => [],
            'comment_notes' => [],
        ]);

        $this->assertTrue($assessment['is_blocking']);
        $labels = collect($assessment['missing'])->pluck('label')->implode(' | ');
        $this->assertStringContainsString('الجمهور المستهدف', $labels);
        $this->assertStringContainsString('ملاحظات بشرية', $labels);
        $this->assertStringContainsString('إشارات تمركز', $labels);
    }

    #[Test]
    public function it_allows_generation_when_core_positioning_signals_exist(): void
    {
        $gate = new StudioTemplateReadinessGate(new StudioTemplateContractRegistry);
        $template = new AITemplate(['code' => 'brand-positioning']);
        $workspace = new Workspace(['name' => 'Rich Workspace']);
        $project = new Project(['name' => 'Rich Project']);

        $assessment = $gate->assess($template, $workspace, $project, [
            'workspace_profile' => [
                'audience' => 'أصحاب العيادات',
                'primary_goal' => 'more_clients',
            ],
            'tool_summaries' => [
                ['tool_code' => 'positioning'],
                ['tool_code' => 'ideal-customer'],
                ['tool_code' => 'offer-builder'],
            ],
            'tool_runs' => [
                ['id' => 1],
            ],
            'client_notes' => ['العميل يريد لغة مباشرة'],
            'approval_notes' => [],
            'comment_notes' => [],
        ]);

        $this->assertFalse($assessment['is_blocking']);
    }

    #[Test]
    public function it_blocks_brand_positioning_when_a_strict_signal_is_missing_even_if_other_context_exists(): void
    {
        $gate = new StudioTemplateReadinessGate(new StudioTemplateContractRegistry);
        $template = new AITemplate(['code' => 'brand-positioning']);
        $workspace = new Workspace(['name' => 'Strict Workspace']);
        $project = new Project(['name' => 'Strict Project']);

        $assessment = $gate->assess($template, $workspace, $project, [
            'workspace_profile' => [
                'audience' => 'أصحاب العيادات الجديدة',
                'primary_goal' => 'more_clients',
            ],
            'tool_summaries' => [
                ['tool_code' => 'package-builder'],
            ],
            'tool_runs' => [
                ['id' => 1],
            ],
            'client_notes' => ['العميل يريد لغة مباشرة'],
            'approval_notes' => [],
            'comment_notes' => [],
        ]);

        $this->assertTrue($assessment['is_blocking']);
        $this->assertContains('positioning_signal', $assessment['strict_blocking_missing_keys']);
    }
}
