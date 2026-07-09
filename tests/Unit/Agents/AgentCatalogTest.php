<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\AgentCatalog;
use App\Domain\AI\Kernel\Agents\AgentDefinition;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentCatalogTest extends TestCase
{
    private function catalog(): AgentCatalog
    {
        return app(AgentCatalog::class);
    }

    #[Test]
    public function it_registers_all_twenty_five_agent_capabilities(): void
    {
        $this->assertCount(25, $this->catalog()->all());
    }

    #[Test]
    public function every_capability_has_a_valid_and_complete_schema(): void
    {
        $clusters = ['intelligence', 'planning', 'creation', 'gate', 'execution', 'memory'];
        $statuses = ['hidden', 'internal', 'beta', 'ga'];

        foreach ($this->catalog()->all() as $code => $definition) {
            $this->assertInstanceOf(AgentDefinition::class, $definition);
            $this->assertNotSame('', $definition->name, "name missing for {$code}");
            $this->assertNotSame('', $definition->summary, "summary missing for {$code}");
            $this->assertContains($definition->cluster, $clusters, "bad cluster for {$code}");
            $this->assertContains($definition->status, $statuses, "bad status for {$code}");
            $this->assertGreaterThanOrEqual(0, $definition->stage);
            $this->assertLessThanOrEqual(5, $definition->stage);
            $this->assertGreaterThanOrEqual(0, $definition->wave);
            $this->assertLessThanOrEqual(3, $definition->wave);
        }
    }

    #[Test]
    public function it_filters_by_stage_cluster_and_persona(): void
    {
        $catalog = $this->catalog();

        $this->assertArrayHasKey('cro_specialist', $catalog->forStage(3));
        $this->assertCount(2, $catalog->forCluster('memory'));
        $this->assertArrayHasKey('agency_operations', $catalog->forPersona('agency'));
        $this->assertArrayNotHasKey('agency_operations', $catalog->forPersona('idea'));
    }

    #[Test]
    public function memory_capabilities_are_infrastructure_without_persona_surface(): void
    {
        $this->assertTrue($this->catalog()->get('intelligence_curator')->isInfrastructure());
        $this->assertTrue($this->catalog()->get('memory_manager')->isInfrastructure());
        $this->assertFalse($this->catalog()->get('content_creator')->isInfrastructure());
    }

    #[Test]
    public function core_capabilities_declare_no_entitlement(): void
    {
        $this->assertTrue($this->catalog()->get('marketing_strategist')->isCore());
        $this->assertTrue($this->catalog()->get('brand_guardian')->isCore());
        $this->assertFalse($this->catalog()->get('content_creator')->isCore());
    }

    #[Test]
    public function hidden_capabilities_are_only_previewable_by_super_admin(): void
    {
        $catalog = $this->catalog();
        $hidden = new AgentDefinition(
            code: 'x', name: 'x', cluster: 'creation', stage: 4,
            entitlement: 'modules.crm', featureFlag: null, personas: ['team'],
            wave: 3, status: 'hidden', surface: '', summary: 'x',
        );

        $this->assertFalse($catalog->decide($hidden, entitled: true, flagOn: true, isSuperAdmin: false));
        $this->assertTrue($catalog->decide($hidden, entitled: false, flagOn: false, isSuperAdmin: true));
    }

    #[Test]
    public function core_ga_capability_is_exposed_regardless_of_entitlement(): void
    {
        $core = new AgentDefinition(
            code: 'x', name: 'x', cluster: 'planning', stage: 0,
            entitlement: null, featureFlag: null, personas: ['idea'],
            wave: 1, status: 'ga', surface: '', summary: 'x',
        );

        $this->assertTrue($this->catalog()->decide($core, entitled: false, flagOn: true, isSuperAdmin: false));
    }

    #[Test]
    public function gated_capability_requires_both_entitlement_and_flag(): void
    {
        $catalog = $this->catalog();
        $gated = new AgentDefinition(
            code: 'x', name: 'x', cluster: 'creation', stage: 4,
            entitlement: 'modules.ai_studio', featureFlag: 'studio.social', personas: ['idea'],
            wave: 2, status: 'beta', surface: '', summary: 'x',
        );

        $this->assertFalse($catalog->decide($gated, entitled: false, flagOn: true, isSuperAdmin: false));
        $this->assertFalse($catalog->decide($gated, entitled: true, flagOn: false, isSuperAdmin: false));
        $this->assertTrue($catalog->decide($gated, entitled: true, flagOn: true, isSuperAdmin: false));
    }
}
